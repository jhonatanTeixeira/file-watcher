<?php

namespace Vox\FileWatcher;

use SplFileInfo;

class FileWatcherEvent
{
    private $path;
    
    private $watchDescriptor;
    
    private $mask;
    
    private $cookie;
    
    private $context;
    
    public function __construct(string $path, array $event, FolderWatcherContext $context)
    {
        $this->path            = new SplFileInfo($path);
        $this->watchDescriptor = $event['wd'];
        $this->mask            = $event['mask'];
        $this->cookie          = $event['cookie'];
        $this->context         = $context;
    }
    
    public function getWatchDescriptor()
    {
        return $this->watchDescriptor;
    }
    
    public function getMask()
    {
        return $this->mask;
    }
    
    public function getCookie(): string
    {
        return $this->cookie;
    }
    
    public function getPath(): SplFileInfo
    {
        return $this->path;
    }
    
    public function isDir(): bool
    {
        return $this->getPath()->isDir();
    }
    
    public function isWatchedDir(): bool
    {
        return in_array($this->getPath()->getPathname(), $this->context->descriptors) ||
            in_array($this->getOriginPath()->getPathname(), $this->context->descriptors);
    }
    
    public function updateDescriptor()
    {
        $folderDescriptor = array_search($this->getOriginPath()->getPathname(), $this->context->descriptors);
        $this->context->descriptors[$folderDescriptor] = $this->getPath()->getPathname();
    }
    
    public function isMovedFile(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['moved_from'] ?? [])
            || in_array($this->path->getPathname(), $this->context->changes['moved_to'] ?? []);
    }
    
    public function isMovedFrom(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['moved_from'] ?? []);
    }
    
    public function isMovedTo(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['moved_to'] ?? []);
    }
    
    public function isCreated(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['created'] ?? []);
    }
    
    public function isModified(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['modified'] ?? []);
    }
    
    public function isDeleted(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['deleted'] ?? []);
    }
    
    public function isOpened(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['opened'] ?? []);
    }
    
    public function isClosed(): bool
    {
        return in_array($this->path->getPathname(), $this->context->changes['closed'] ?? []);
    }
    
    public function hasOriginPath(): bool
    {
        return isset($this->context->changes['cookies'][$this->cookie]['from']);
    }
    
    public function getOriginPath(): ?SplFileInfo
    {
        if (!$this->hasOriginPath()) {
            return null;
        }
        
        return new SplFileInfo($this->context->changes['cookies'][$this->cookie]['from']);
    }
}
