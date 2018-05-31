<?php

namespace Vox\FileWatcher;

class FolderWatcherContext
{
    public $changes = [];
    
    public $rawEvents = [];
    
    public $descriptors = [];
    
    public $recursive = false;
    
    public $blocking = false;
}
