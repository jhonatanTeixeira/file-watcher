<?php

namespace Vox\FileWatcher;

use DirectoryIterator;

class FolderWatcher
{
    private $folder;

    private $blocking;

    private $context;

    private $resource;
    
    private $createdCallback;

    private $deletedCallback;

    private $modifiedCallback;

    private $movedCallback;
    
    private $openedCallback;
    
    private $closedCallback;

    public function __construct(string $folder, FolderWatcherContext $context, $inotifyResource = null)
    {
        $this->folder     = $folder;
        $this->context    = $context;
        $this->resource   = $inotifyResource ?? inotify_init();
        $this->blocking   = $this->context->blocking ?? false;

        if (!$this->blocking) {
            $read   = [$this->resource];
            $write  = null;
            $except = null;

            stream_select($read, $write, $except, 0);
            stream_set_blocking($this->resource, false);
        }

        $this->watchFolders($folder);
    }

    private function watchFolders($folder)
    {
        $descriptor = inotify_add_watch($this->resource, $folder, IN_ALL_EVENTS);

        $this->context->descriptors[$descriptor] = $folder;
        
        if (!$this->context->recursive) {
            return;
        }

        $directoryIterator = new DirectoryIterator($folder);
        
        foreach ($directoryIterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }
            
            $this->watchFolders($directory->getPathname());
        }
    }

    public function readEvents()
    {
        $rawEvents = inotify_read($this->resource) ?: [];
        $this->context->rawEvents = $rawEvents;

        $events  = [];

        foreach ($rawEvents as $event) {
            $name = sprintf('%s/%s', preg_replace('/\/$/', '', $this->context->descriptors[$event['wd']]), $event['name']);
            $inExpectedEvent = false;

            switch ($event['mask']) {
                case IN_CREATE:
                case IN_CREATE | IN_ISDIR:
                    $this->context->changes['created'][] = $name;
                    $inExpectedEvent = true;

                    if (is_dir($name) && $this->context->recursive) {
                        $this->watchFolders($name);
                    }

                    break;
                case IN_MODIFY:
                    $this->context->changes['modified'][]   = $name;
                    $inExpectedEvent         = true;
                    break;
                case IN_OPEN:
                    $this->context->changes['opened'][]     = $name;
                    $inExpectedEvent         = true;
                    break;
                case IN_CLOSE_WRITE:
                    $this->context->changes['modified'][]   = $name;
                case IN_CLOSE:
                    $this->context->changes['closed'][]     = $name;
                    $inExpectedEvent         = true;
                    break;
                case IN_MOVE:
                case IN_MOVE | IN_ISDIR:
                    $this->context->changes['moved'][]      = $name;
                    $inExpectedEvent         = true;
                    break;
                case IN_MOVED_FROM:
                case IN_MOVED_FROM | IN_ISDIR:
                    $this->context->changes['moved_from'][] = $name;
                    $this->context->changes['cookies'][$event['cookie']]['from'] = $name;
                    $inExpectedEvent         = false;
                    break;
                case IN_MOVED_TO:
                case IN_MOVED_TO | IN_ISDIR:
                    $this->context->changes['moved_to'][]   = $name;
                    $this->context->changes['cookies'][$event['cookie']]['to'] = $name;
                    $inExpectedEvent         = true;
                    break;
                case IN_DELETE:
                case IN_DELETE | IN_ISDIR:
                    $this->context->changes['deleted'][]    = $name;
                    $inExpectedEvent         = true;
                    break;
            }
            
            if ($inExpectedEvent) {
                $events[$name] = new FileWatcherEvent($name, $event, $this->context);
            }
        }
        
        return $events;
    }
    
    public function dispatchEvents()
    {
        $events = $this->readEvents();

        foreach ($events as $event) {
            if ($event->isCreated() && isset($this->createdCallback)) {
                ($this->createdCallback)($event, $this->context);
            }
            
            if ($event->isMovedFile()) {
                if ($event->isWatchedDir()) {
                    $event->updateDescriptor();
                }
                
                if (isset($this->movedCallback)) {
                    ($this->movedCallback)($event, $this->context);
                }
            }
            
            if ($event->isOpened() && !$event->isDeleted() && isset($this->openedCallback)) {
                ($this->openedCallback)($event, $this->context);
            }

            if ($event->isModified() && isset($this->modifiedCallback)) {
                ($this->modifiedCallback)($event, $this->context);
            }
            
            if ($event->isClosed() && isset($this->closedCallback)) {
                ($this->closedCallback)($event, $this->context);
            }

            if ($event->isDeleted() && isset($this->deletedCallback)) {
                ($this->deletedCallback)($event, $this->context);
            }
        }
        
        $this->context->changes = [];
    }
    
    public function onCreated(callable $createdCallback)
    {
        $this->createdCallback = $createdCallback;
    }

    public function onDeleted(callable $deletedCallback)
    {
        $this->deletedCallback = $deletedCallback;
    }

    public function onModified(callable $modifiedCallback)
    {
        $this->modifiedCallback = $modifiedCallback;
    }

    public function onMoved(callable $movedCallback)
    {
        $this->movedCallback = $movedCallback;
    }
    
    public function onOpened(callable $openedCallback)
    {
        $this->openedCallback = $openedCallback;
    }

    public function onClosed(callable $closedCallback)
    {
        $this->closedCallback = $closedCallback;
    }
}
