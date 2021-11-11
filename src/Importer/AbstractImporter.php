<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;

/**
 * Permit to find error in image or link.
 */
abstract class AbstractImporter
{
    protected string $entityClass;

    protected \Doctrine\ORM\EntityManagerInterface $em;

    protected \Pushword\Core\Component\App\AppPool $apps;

    public function __construct(
        EntityManagerInterface $entityManager,
        AppPool $appPool,
        string $entityClass
    ) {
        $this->entityClass = $entityClass;
        $this->apps = $appPool;
        $this->em = $entityManager;
    }

    abstract public function import(string $filePath, DateTimeInterface $dateTime);

    public function finishImport()
    {
        $this->em->flush();
    }

    protected static function underscoreToCamelCase(string $string): string
    {
        $str = str_replace('_', '', ucwords($string, '_'));

        return lcfirst($str);
    }
}
