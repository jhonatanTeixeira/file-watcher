# PHP Inotify File Watcher

## Installing

this library requires PHP 7.1+ and pecl inotify extension inotify

```
$ sudo pecl install inotify
$ composer require vox/file-watcher
```

## Usage

Usage is simple, first configure the context:

```php
use Vox\FileWatcher\FolderWatcherContext;
use Vox\FileWatcher\FolderWatcher;

$context = new FolderWatcherContext();
$context->blocking = true;  //if set to false, php will not block when readEvents is called, async code can be done
$context->recursive = true; //if set to true subfolders will be also watched, 
                            //and so new subfolders will be automatically be watched
```
Then obtain the watcher instance:

```php
$watcher = new FolderWatcher('/folder/to/watch', $context);
```

Now, all you need is the callback actions for each possible event, like this:

```php
$watcher->onCreated(function (FileWatcherEvent $event, FolderWatcherContext $context) {
    //do something when a file is created
});
```
there are other events to be listened:
* onMoved
* onOpened
* onModified
* onClosed
* onDeleted

Finaly call the dispatch event method to listen to filesystem changes:

```php
$watcher->dispatchEvents();
```

Note that, this operation will block untill an event occurs, and returns imediately as soon an filesystem event on the watched folder happens. This block can be avoided setting the context blocking property to false, that way, if no event ocurred between the watcher instancing and the call of this method an empty array will be returned

### The readEvents method
You may notice that the FolderWatcher class has a readEvents method, this method can be called in case you may need to get the events without dispatching the events. this method returns an array of Vox\FileWatcher\FileWatcherEvent, this class has various methods to help identify what is this event about:
* isDir
* isWatchedDir - returns true if this event refers to a dir wihch is currently beig watched
* isMovedFile
* isMovedFrom
* isMovedTo
* isCreated
* isModified
* isDeleted
* isOpened
* isClosed
* getOriginPath - if its a moved file, get the former path as SplFileInfo instance
* getPath - the file path as SplFileInfo instance
* getCookie - if the event has a cookie, than its a moved file, the cookie is what binds the moved from and moved to events

Note: not all the movedTo files has a origin path, only those that are moved between watched dirs, when a file comes from a directory wihch is not being watched it will have no moved from event, therefore no originPath
