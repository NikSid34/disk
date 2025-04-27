<?php


namespace app\enum;


enum Endpoints: string {
    case Disk = '/disk/';
    case CreateFolder = '/disk/createFolder/';
    case UploadFile = '/disk/uploadFile/';
}