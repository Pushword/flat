<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeInterface;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Repository\Repository;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Permit to find error in image or link.
 *
 * @extends AbstractImporter<MediaInterface>
 */
class MediaImporter extends AbstractImporter
{
    use ImageImporterTrait;

    protected ?string $mediaDir = null;

    /**
     * @var string
     */
    protected $projectDir;

    private bool $newMedia = false;

    /** @required */
    public function setMediaDir(string $mediaDir): self
    {
        $this->mediaDir = $mediaDir;

        return $this;
    }

    /** @required */
    public function setProjectDir(string $projectDir): self
    {
        $this->projectDir = $projectDir;

        return $this;
    }

    public function import(string $filePath, DateTimeInterface $dateTime): void
    {
        if (! $this->isImage($filePath)) {
            if (str_ends_with($filePath, '.json') && file_exists(\Safe\substr($filePath, 0, -5))) { // data file
                return;
            }

            $this->importMedia($filePath, $dateTime);

            return;
        }

        $this->importImage($filePath, $dateTime);
    }

    private function isImage(string $filePath): bool
    {
        return false !== getimagesize($filePath);
        //0 !== strpos(finfo_file(finfo_open(\FILEINFO_MIME_TYPE), $filePath), 'image/') || preg_match('/\.webp$/', $filePath);
    }

    public function importMedia(string $filePath, DateTimeInterface $dateTime): void
    {
        $media = $this->getMedia($this->getFilename($filePath));

        if (1 == 2 && ! $this->newMedia && $media->getUpdatedAt() >= $dateTime) {
            return; // no update needed
        }

        $filePath = $this->copyToMediaDir($filePath);

        $media
            ->setProjectDir($this->projectDir)
            ->setStoreIn(\dirname($filePath))
            ->setSize(\Safe\filesize($filePath))
            ->setMimeType(finfo_file(\Safe\finfo_open(\FILEINFO_MIME_TYPE), $filePath));

        $data = $this->getData($filePath);

        $this->setData($media, $data);
    }

    private function setData(MediaInterface $media, array $data): void
    {
        $media->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = self::underscoreToCamelCase($key);

            $setter = 'set'.ucfirst($key);
            if (method_exists($media, $setter)) {
                if (\in_array($key, ['createdAt', 'updatedAt'])) {
                    $value = new DateTime($value['date']);
                }

                $media->$setter($value);

                continue;
            }

            $media->setCustomProperty($key, $value);
        }

        if ($this->newMedia) {
            $this->em->persist($media);
        }
    }

    /**
     * @return mixed[]
     */
    private function getData(string $filePath): array
    {
        if (! file_exists($filePath.'.json')) {
            return [];
        }

        $jsonData = \Safe\json_decode(\Safe\file_get_contents($filePath.'.json'), true);

        return \is_array($jsonData) ? $jsonData : [];
    }

    public function getFilename($filePath): string
    {
        return str_replace(\dirname($filePath).'/', '', $filePath);
    }

    private function copyToMediaDir(string $filePath): string
    {
        $newFilePath = $this->mediaDir.'/'.$this->getFilename($filePath);

        if (null !== $this->mediaDir && $filePath != $newFilePath) {
            (new Filesystem())->copy($filePath, $newFilePath);

            return $newFilePath;
        }

        return $filePath;
    }

    private function getMedia(string $media): MediaInterface
    {
        $mediaEntity = Repository::getMediaRepository($this->em, $this->entityClass)->findOneBy(['media' => $media]);
        $this->newMedia = false;

        if (null === $mediaEntity) {
            $this->newMedia = true;
            $mediaClass = $this->entityClass;
            $mediaEntity = new $mediaClass();
            $mediaEntity
                ->setMedia($media)
                ->setName($media.' - '.uniqid());
        }

        return $mediaEntity;
    }
}
