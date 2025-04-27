<?php


namespace app\enum;


enum FileIndexStatus: int {
    case Pending = 0;
    case Processing = 1;
    case Indexed = 2;
}
