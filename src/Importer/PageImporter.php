<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeInterface;
use Exception;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Flat\FlatFileContentDirFinder;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/**
 * Permit to find error in image or link.
 */
class PageImporter extends AbstractImporter
{
    protected ?array $pages = null;

    protected array $toAddAtTheEnd = [];

    protected ?\Pushword\Flat\FlatFileContentDirFinder $contentDirFinder = null;

    protected string $mediaClass;

    private bool $newPage = false;

    public function setContentDirFinder(FlatFileContentDirFinder $flatFileContentDirFinder)
    {
        $this->contentDirFinder = $flatFileContentDirFinder;
    }

    public function setMediaClass(string $mediaClass)
    {
        $this->mediaClass = $mediaClass;
    }

    private function getContentDir()
    {
        $host = $this->apps->get()->getMainHost();

        return $this->contentDirFinder->get($host);
    }

    public function import(string $filePath, DateTimeInterface $dateTime): void
    {
        if (0 !== strpos(finfo_file(\Safe\finfo_open(\FILEINFO_MIME_TYPE), $filePath), 'text/')) {
            return;
        }

        $content = \Safe\file_get_contents($filePath);
        $document = YamlFrontMatter::parse($content);

        if (empty($document->matter())) {
            return; //throw new Exception('No content found in `'.$filePath.'`');
        }

        $slug = $document->matter('slug') ?? $this->filePathToSlug($filePath);

        $this->editPage($slug, $document->matter(), $document->body(), $dateTime);
    }

    private function filePathToSlug($filePath): string
    {
        $slug = \Safe\preg_replace('/\.md$/i', '', str_replace($this->getContentDir().'/', '', $filePath));

        if ('index' == $slug) {
            $slug = 'homepage';
        } elseif ('index' == basename($slug)) {
            $slug = \Safe\substr($slug, 0, -\strlen('index'));
        }

        return Page::normalizeSlug($slug);
    }

    private function getPageFromSlug($slug): PageInterface
    {
        $page = $this->getPage($slug);
        $this->newPage = false;

        if (! $page) {
            $pageClass = $this->entityClass;
            $page = new $pageClass();
            $this->newPage = true;
        }

        return $page;
    }

    private function editPage(string $slug, array $data, string $content, DateTime $dateTime)
    {
        $page = $this->getPageFromSlug($slug);

        if (false === $this->newPage && $page->getUpdatedAt() >= $dateTime) {
            return; // no update needed
        }

        $page->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = $this->normalizePropertyName($key);
            $camelKey = self::underscoreToCamelCase($key);

            if (\in_array($camelKey, array_keys($this->getObjectRequiredProperties()))) {
                $this->toAddAtTheEnd[$slug] = array_merge($this->toAddAtTheEnd[$slug] ?? [], [$camelKey => $value]);

                continue;
            }
            $setter = 'set'.ucfirst($camelKey);
            if (method_exists($page, $setter)) {
                if (\in_array($camelKey, ['publishedAt', 'createdAt', 'updatedAt'])) {
                    $value = new DateTime($value);
                }

                $page->$setter($value);

                continue;
            }
            $page->setCustomProperty($key, $value);
        }

        $page->setHost($this->apps->get()->getMainHost());
        $page->setSlug($slug);
        if (! $page->getLocale()) {
            $page->setLocale($this->apps->get()->getLocale());
        }
        $page->setMainContent($content);

        if (true === $this->newPage) {
            $this->em->persist($page);
        }
    }

    private function normalizePropertyName(string $propertyName)
    {
        if ('parent' == $propertyName) {
            $propertyName = 'parentPage';
        }

        return $propertyName;
    }

    private function toAddAtTheEnd()
    {
        foreach ($this->toAddAtTheEnd as $slug => $data) {
            $page = $this->getPage($slug);
            foreach ($data as $property => $value) {
                $object = $this->getObjectRequiredProperties($property);

                if (PageInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $page->$setter($this->getPage($value));

                    continue;
                }

                if (MediaInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $mediaName = \Safe\preg_replace('@^/?media/(default)?/@', '', $value);
                    $media = $this->getMedia($mediaName);
                    if (null === $media) {
                        throw new Exception('Media `'.$value.'` ('.$mediaName.') not found in `'.$slug.'`.');
                    }
                    $page->$setter($media);

                    continue;
                }

                $this->$object($page, $property, $value);
            }
        }
    }

    private function addPages(PageInterface $page, string $property, array $pages)
    {
        $setter = 'set'.ucfirst($property);
        $this->$setter([]);
        foreach ($pages as $p) {
            $adder = 'add'.ucfirst($property);
            $page->$adder($this->getPage($p));
        }
    }

    public function finishImport()
    {
        $this->em->flush();

        $this->getPages(false);
        $this->toAddAtTheEnd();

        $this->em->flush();
    }

    /**
     * Todo, get them automatically.
     *
     * @return array|string
     */
    private function getObjectRequiredProperties($key = null)
    {
        $properties = [
            'extendedPage' => PageInterface::class,
            'parentPage' => PageInterface::class,
            'translations' => 'addPages',
            'mainImage' => MediaInterface::class,
        ];

        if (null === $key) {
            return $properties;
        }

        return $properties[$key];
    }

    private function getMedia($media)
    {
        return Repository::getMediaRepository($this->em, $this->mediaClass)->findOneBy(['media' => $media]);
    }

    private function getPage($slug): ?PageInterface
    {
        if (\is_array($slug)) {
            return Repository::getPageRepository($this->em, $this->entityClass)->findOneBy($slug);
        }

        $pages = array_filter($this->getPages(), fn ($page) => $page->getSlug() == $slug);
        $pages = array_values($pages);

        return $pages[0] ?? null;
    }

    private function getPages($cache = true): array
    {
        if (true === $cache && $this->pages) {
            return $this->pages;
        }
        $repo = Repository::getPageRepository($this->em, $this->entityClass);

        return $this->pages = $repo->findByHost($this->apps->get()->getMainHost());
    }
}
