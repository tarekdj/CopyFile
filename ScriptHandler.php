<?php

namespace SlowProg\CopyFile;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Composer\Script\Event;

class ScriptHandler
{
    public static function copyFiles($from, $to, $fs, $io) 
    {
        // Check the renaming of file for direct moving (file-to-file)
        $isRenameFile = substr($to, -1) != '/' && !is_dir($from);

        if (file_exists($to) && !is_dir($to) && !$isRenameFile) {
            throw new \InvalidArgumentException('Destination directory is not a directory.');
        }

        try {
            if ($isRenameFile) {
                $fs->mkdir(dirname($to));
            } else {
                $fs->mkdir($to);
            }
        } catch (IOException $e) {
            throw new \InvalidArgumentException(sprintf('<error>Could not create directory %s.</error>', $to));
        }

        if (false === file_exists($from)) {
            throw new \InvalidArgumentException(sprintf('<error>Source directory or file "%s" does not exist.</error>', $from));
        }

        if (is_dir($from)) {
            $finder = new Finder;
            $finder->files()->in($from);

            foreach ($finder as $file) {
                $dest = sprintf('%s/%s', $to, $file->getRelativePathname());

                try {
                    $fs->copy($file, $dest);
                } catch (IOException $e) {
                    throw new \InvalidArgumentException(sprintf('<error>Could not copy %s</error>', $file->getBaseName()));
                }
            }
        } else {
            try {
                if ($isRenameFile) {
                    $fs->copy($from, $to);
                } else {
                    $fs->copy($from, $to.'/'.basename($from));
                }
            } catch (IOException $e) {
                throw new \InvalidArgumentException(sprintf('<error>Could not copy %s</error>', $from));
            }
        }

        $io->write(sprintf('Copied file(s) from <comment>%s</comment> to <comment>%s</comment>.', $from, $to));
    }
    /**
     * @param Event $event
     */
    public static function copy(Event $event)
    {
        $extras = $event->getComposer()->getPackage()->getExtra();

        if (!isset($extras['copy-file'])) {
            throw new \InvalidArgumentException('The dirs or files needs to be configured through the extra.copy-file setting.');
        }

        $files = $extras['copy-file'];

        if ($files === array_values($files)) {
            throw new \InvalidArgumentException('The extra.copy-file must be hash like "{<dir_or_file_from>: <dir_to>}".');
        }

        $fs = new Filesystem;
        $io = $event->getIO();

        foreach ($files as $from => $to) {
            $matches_in_values = [];
            $matches_in_keys = [];
            if (!is_array($to)) {
                preg_match_all('/{[a-z,0-9]*}/', $to, $matches_in_values);
                preg_match_all('/{[a-z,0-9]*}/', $from, $matches_in_keys);               
            }

            if ($matches_in_values || $matches_in_keys) {
                $matches = array_merge($matches_in_keys[0], $matches_in_values[0]);
                $mapping_array = isset($extras['copy-file-mapping']) ? $extras['copy-file-mapping'] : [];
                // Implement file mapping.
                if (!empty($matches) && !empty($mapping_array)) {
                     var_dump("$from => $to");
                    foreach ($mapping_array as $key => $values) {
                        $from_ = $from;
                        $to_ = $to;
                        foreach ($values as $k => $v) {
                            $from_ = str_replace("{".$k."}", $v, $from_);
                            $to_ = str_replace("{".$k."}", $v, $to_);
                        }
                        self::copyFiles($from, $to_, $fs, $io);
                        
                    }
                }
            }
            elseif (is_array($to)) {
                // Implement one to many destinations.
                foreach ($to as $to_) {
                    self::copyFiles($from, $to_, $fs, $io);
                }
            }
            else {
                self::copyFiles($from, $to, $fs, $io);
            }
        }
    }
}
