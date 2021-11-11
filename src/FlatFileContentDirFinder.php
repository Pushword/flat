<?php

namespace Pushword\Flat;

use Exception;
use Pushword\Core\Component\App\AppPool;

/**
 * Permit to find error in image or link.
 */
class FlatFileContentDirFinder
{
    protected \Pushword\Core\Component\App\AppPool $apps;

    protected string $projectDir;

    protected array $contentDir = [];

    public function __construct(
        AppPool $apps,
        string $projectDir
    ) {
        $this->projectDir = $projectDir;
        $this->apps = $apps;
    }

    public function get(string $host): string
    {
        if (isset($this->contentDir[$host])) {
            return $this->contentDir[$host];
        }

        $app = $this->apps->get($host);

        $dir = $app->get('flat_content_dir');
        if (! $dir) {
            throw new Exception('No `flat_content_dir` dir in `'.$app->getMainHost().'`\'s params.');
        }

        $this->contentDir[$host] = $dir;

        if (! file_exists($this->contentDir[$host])) {
            throw new Exception('Content dir `'.$dir.'` not found.');
        }

        return $this->contentDir[$host];
    }

    public function has(string $host): bool
    {
        if (isset($this->contentDir[$host])) {
            return $this->contentDir[$host];
        }

        $app = $this->apps->get($host);

        $dir = $app->get('flat_content_dir');
        if (! $dir) {
            return false;
        }

        return true;
    }
}
