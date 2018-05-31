<?php

namespace Vox\FileWatcher;

class FolderWatcherTest extends \PHPUnit\Framework\TestCase
{
    private $rootFolder = '/tmp/file-watcher-test';
    
    private $assertions;
    
    protected function setUp()
    {
        exec("rm -rf {$this->rootFolder}");
        
        mkdir($this->rootFolder);
        mkdir("{$this->rootFolder}/foo");
        mkdir("{$this->rootFolder}/bar");
        $this->assertions = 0;
    }
    
    public function testShouldWatchFileEvents()
    {
        $context = new FolderWatcherContext();
        $context->blocking = true;
        $context->recursive = true;
        
        $watcher = new FolderWatcher($this->rootFolder, $context);
        
        $watcher->onCreated(function (FileWatcherEvent $event, FolderWatcherContext $context) {
            $this->assertEquals("$this->rootFolder/foo.txt", $event->getPath()->getPathname());
            $this->assertions++;
            $this->assertEquals(1, $this->assertions);
        });
        
        touch("{$this->rootFolder}/foo.txt");
        
        $watcher->dispatchEvents();
        
        $watcher->onMoved(function (FileWatcherEvent $event, FolderWatcherContext $context) {
            $this->assertEquals("$this->rootFolder/bar/foo.txt", $event->getPath()->getPathname());
            $this->assertEquals("$this->rootFolder/foo.txt", $event->getOriginPath()->getPathname());
            $this->assertions++;
            $this->assertEquals(2, $this->assertions);
        });
        
        rename("{$this->rootFolder}/foo.txt", "{$this->rootFolder}/bar/foo.txt");
        
        $watcher->dispatchEvents();
        
        $watcher->onOpened(function (FileWatcherEvent $event, FolderWatcherContext $context) {
            $this->assertEquals("$this->rootFolder/bar/foo.txt", $event->getPath()->getPathname());
            $this->assertions++;
            $this->assertEquals(3, $this->assertions);
        });
        
        $watcher->onModified(function (FileWatcherEvent $event, FolderWatcherContext $context) {
            $this->assertEquals("$this->rootFolder/bar/foo.txt", $event->getPath()->getPathname());
            $this->assertEquals("lorem ipsum", $event->getPath()->openFile()->fgets());
            $this->assertions++;
            $this->assertEquals(4, $this->assertions);
        });

        $watcher->onClosed(function (FileWatcherEvent $event, FolderWatcherContext $context) {
            $this->assertEquals("$this->rootFolder/bar/foo.txt", $event->getPath()->getPathname());
            $this->assertions++;
            $this->assertEquals(5, $this->assertions);
        });
        
        file_put_contents("$this->rootFolder/bar/foo.txt", "lorem ipsum", FILE_APPEND);
        
        $watcher->dispatchEvents();

        $watcher->onDeleted(function (FileWatcherEvent $event, FolderWatcherContext $context) {
            $this->assertEquals("$this->rootFolder/bar/foo.txt", $event->getPath()->getPathname());
            $this->assertions++;
            $this->assertEquals(6, $this->assertions);
        });
        
        unlink("$this->rootFolder/bar/foo.txt");
        
        $watcher->dispatchEvents();
        
        $this->assertEquals(6, $this->assertions);
    }
    
    public function testShouldWatchDirectoryEvents()
    {
        $folderName = "$this->rootFolder/baz";
        
        $context = new FolderWatcherContext();
        $context->blocking = true;
        $context->recursive = true;
        
        $watcher = new FolderWatcher($this->rootFolder, $context);
        
        $created = 0;
        $moved = 0;
        
        $watcher->onCreated(function (FileWatcherEvent $event, FolderWatcherContext $context) use (&$created) {
            $created++;
        });
        
        mkdir($folderName);
        $watcher->dispatchEvents();
        $this->assertEquals(1, $created);
        
        touch("$folderName/new");
        $watcher->dispatchEvents();
        $this->assertEquals(2, $created);
        
        $watcher->onMoved(function (FileWatcherEvent $event) use (&$moved) {
            $moved++;
        });
        
        $descriptor = array_search($folderName, $context->descriptors);
        $newFolderName = "$this->rootFolder/bar/baz";
        rename($folderName, $newFolderName);
        
        $watcher->dispatchEvents();
        
        $this->assertEquals($newFolderName, $context->descriptors[$descriptor]);
        $this->assertEquals(1, $moved);
        
        touch("$newFolderName/new2");
        
        $watcher->dispatchEvents();
        
        $this->assertEquals(3, $created);
    }
}
