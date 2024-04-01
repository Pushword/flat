<?php

namespace Pushword\Flat\Importer;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;

use function Safe\file_get_contents;
use function Safe\filesize;
use function Safe\json_decode;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Permit to find error in image or link.
 *
 * @extends AbstractImporter<Media>
 */
class MediaImporter extends AbstractImporter
{
    use ImageImporterTrait;

    public function __construct(
        protected EntityManagerInterface $em,
        protected AppPool $apps,
        public string $mediaDir,
        public string $projectDir
    ) {
        parent::__construct($em, $apps);
    }

    private bool $newMedia = false;

    public function import(string $filePath, \DateTimeInterface $lastEditDateTime): void
    {
        if (! $this->isImage($filePath)) {
            if (str_ends_with($filePath, '.json') && file_exists(substr($filePath, 0, -5))) { // data file
                return;
            }

            $this->importMedia($filePath, $lastEditDateTime);

            return;
        }

        $this->importImage($filePath, $lastEditDateTime);
    }

    private function isImage(string $filePath): bool
    {
        return false !== getimagesize($filePath);
        // 0 !== strpos(finfo_file(finfo_open(\FILEINFO_MIME_TYPE), $filePath), 'image/') || preg_match('/\.webp$/', $filePath);
    }

    public function importMedia(string $filePath, \DateTimeInterface $dateTime): void
    {
        $media = $this->getMedia($this->getFilename($filePath));

        if (! $this->newMedia && $media->getUpdatedAt() >= $dateTime) {
            return; // no update needed
        }

        $filePath = $this->copyToMediaDir($filePath);

        $media
            ->setProjectDir($this->projectDir)
            ->setStoreIn(\dirname($filePath))
            ->setSize(filesize($filePath))
            ->setMimeType($this->getMimeTypeFromFile($filePath));

        $data = $this->getData($filePath);

        $this->setData($media, $data);
    }

    /**
     * @param array<mixed> $data
     */
    private function setData(Media $media, array $data): void
    {
        $media->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = self::underscoreToCamelCase((string) $key);

            $setter = 'set'.ucfirst($key);
            if (method_exists($media, $setter)) {
                if (\in_array($key, ['createdAt', 'updatedAt'], true)
                    && \is_array($value) && isset($value['date']) && \is_string($value['date'])) {
                    $value = new \DateTime($value['date']);
                }

                $media->$setter($value); // @phpstan-ignore-line

                continue;
            }

            $media->setCustomProperty($key, $value);
        }

        if ($this->newMedia) {
            $this->em->persist($media);
        }
    }

    /** @return array<string|int, mixed> */
    private function getData(string $filePath): array
    {
        if (! file_exists($filePath.'.json')) {
            return [];
        }

        $jsonData = json_decode(file_get_contents($filePath.'.json'), true);

        return \is_array($jsonData) ? $jsonData : [];
    }

    public function getFilename(string $filePath): string
    {
        return str_replace(\dirname($filePath).'/', '', $filePath);
    }

    private function copyToMediaDir(string $filePath): string
    {
        $newFilePath = $this->mediaDir.'/'.$this->getFilename($filePath);

        if ('' !== $this->mediaDir && $filePath !== $newFilePath) {
            (new Filesystem())->copy($filePath, $newFilePath);

            return $newFilePath;
        }

        return $filePath;
    }

    protected function getMedia(string $media): Media
    {
        $mediaEntity = $this->em->getRepository(Media::class)->findOneBy(['media' => $media]);
        $this->newMedia = false;

        if (! $mediaEntity instanceof Media) {
            $this->newMedia = true;
            $mediaEntity = new Media();
            $mediaEntity
                ->setMedia($media)
                ->setName($media.' - '.uniqid());
        }

        return $mediaEntity;
    }
}
