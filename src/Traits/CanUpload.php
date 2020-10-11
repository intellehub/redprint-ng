<?php 

namespace Shahnewaz\RedprintNg\Traits;

use Intervention\Image\ImageManagerStatic as Image;

trait CanUpload {

    public $file;
    public $dir;
    public $saveFileName;
    public $destination;
    public $editableInstance;

    public function upload ($file, $dir = null) {

        $this->file = $file;
        $this->dir = $dir;

        $fileName = str_random(8);
        $fileExtension = $this->file->getClientOriginalExtension();
        $isImage = substr($this->file->getMimeType(), 0, 5) === 'image';
        $uploadsHome = "/uploads/";

        $this->saveFileName = $fileName. "." . $fileExtension;
        $this->dir = $this->dir ? $uploadsHome.$this->dir."/" : $uploadsHome; 
        $this->destination = public_path($this->dir);
        $this->file->move($this->destination, $this->saveFileName);
        if($isImage) {
            $this->editableInstance = Image::make(public_path($this->dir. '/'. $this->saveFileName));
        } else {
            $this->editableInstance = null;
        }
        return $this;
    }

    public function watermark ($filePath = null) {
        if (!$filePath || !$this->editableInstance) { return $this; }
        $this->editableInstance
            ->insert($filePath, 'bottom-right', 10, 10)
            ->save();
        return $this;
    }

    public function greyscale () {
        if (!$this->editableInstance) { return $this; }
        $this->editableInstance
            ->greyscale()
            ->save();
        return $this;
    }

    public function invert () {
        if (!$this->editableInstance) { return $this; }
        $this->editableInstance
            ->invert()
            ->save();
        return $this;
    }

    public function getFileName() {
        $this->editableInstance = null;
        return $this->saveFileName;
    }
}