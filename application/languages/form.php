<?php

return array (
    Zend_Validate_NotEmpty::IS_EMPTY                => 'заполните обязательное поле',
    
    Zend_Validate_EmailAddress::INVALID             => "'%value%' - это не корректный адрес e-mail",
    Zend_Validate_EmailAddress::INVALID_HOSTNAME    => "'%hostname%' - это не корректное имя хоста",
    Zend_Validate_EmailAddress::INVALID_MX_RECORD   => "'%hostname%' does not appear to have a valid MX record for the email address '%value%'",
    Zend_Validate_EmailAddress::DOT_ATOM            => "'%localPart%' не соответствует формату",
    Zend_Validate_EmailAddress::QUOTED_STRING       => "'%localPart%' not matched against quoted-string format",
    Zend_Validate_EmailAddress::INVALID_LOCAL_PART  => "'%localPart%' это не корректная локальная часть адреса '%value%'",
    
    Zend_Validate_Hostname::IP_ADDRESS_NOT_ALLOWED  => "'%value%' appears to be an IP address, but IP addresses are not allowed",
    Zend_Validate_Hostname::UNKNOWN_TLD             => "'%value%' appears to be a DNS hostname but cannot match TLD against known list",
    Zend_Validate_Hostname::INVALID_DASH            => "'%value%' appears to be a DNS hostname but contains a dash (-) in an invalid position",
    Zend_Validate_Hostname::INVALID_HOSTNAME_SCHEMA => "'%value%' appears to be a DNS hostname but cannot match against hostname schema for TLD '%tld%'",
    Zend_Validate_Hostname::UNDECIPHERABLE_TLD      => "'%value%' appears to be a DNS hostname but cannot extract TLD part",
    Zend_Validate_Hostname::INVALID_HOSTNAME        => "'%value%' не соответствует ожидаемой структуре DNS хоста",
    Zend_Validate_Hostname::INVALID_LOCAL_NAME      => "'%value%' does not appear to be a valid local network name",
    Zend_Validate_Hostname::LOCAL_NAME_NOT_ALLOWED  => "'%value%' - это адрес внутри локальной сети, что недопустимо",
    
    Zend_Validate_Date::INVALID        => "не является верной датой",
    Zend_Validate_Date::FALSEFORMAT    => "не соответствует формату",
    
    Zend_Validate_StringLength::TOO_SHORT => "значение меньше минимальной длины (%min%)",
    Zend_Validate_StringLength::TOO_LONG  => "значение больше максимальной длины (%max%)",
    
    Zend_Validate_Between::NOT_BETWEEN        => "значение должно быть в диапазоне от %min% до %max%, включительно",
    Zend_Validate_Between::NOT_BETWEEN_STRICT => "'%value%' is not strictly between '%min%' and '%max%'",
    
    Zend_Validate_Int::NOT_INT => "'%value%' не является целым числом",
    
    
    Zend_Validate_File_Upload::INI_SIZE => "Файл '%value%' превышает заданый размер",
    Zend_Validate_File_Upload::FORM_SIZE => "Файл '%value%' превышает заданый лимит размера файла",
    Zend_Validate_File_Upload::PARTIAL => "Файл '%value%' был загружен частично",
    Zend_Validate_File_Upload::NO_FILE => "Файл '%value%' не был загружен",
    Zend_Validate_File_Upload::NO_TMP_DIR => "Не было найдено временной директории для файла '%value%'",
    Zend_Validate_File_Upload::CANT_WRITE => "Файл '%value%' не может быть записан",
    Zend_Validate_File_Upload::EXTENSION => "Дополнение вернуло ошибку, переслав файл '%value%'",
    Zend_Validate_File_Upload::ATTACK => "Файл '%value%' был загружен неразрешенным методом",
    Zend_Validate_File_Upload::FILE_NOT_FOUND => "Файл '%value%' не был найден",
    Zend_Validate_File_Upload::UNKNOWN => "Возникла неизвестная ошибка при загрузке файла '%value%'",
    Zend_Validate_File_Size::TOO_BIG => "Файл '%value%' имеет слишком большой размер",
    Zend_Validate_File_Size::TOO_SMALL => "Файл '%value%' слишком маленького размера",
    Zend_Validate_File_Size::NOT_FOUND => "Файл '%value%' не найден",
    Zend_Validate_File_ImageSize::WIDTH_TOO_BIG => "Ширина загруженного файла '%value%' слишком большая",
    Zend_Validate_File_ImageSize::WIDTH_TOO_SMALL => "Ширина загруженного файла '%value%' слишком маленькая",
    Zend_Validate_File_ImageSize::HEIGHT_TOO_BIG => "Высота загруженного файла '%value%' слишком большая",
    Zend_Validate_File_ImageSize::HEIGHT_TOO_SMALL => "Высота загруженного файла '%value%' слишком маленькая",
    Zend_Validate_File_ImageSize::NOT_DETECTED => "Размеры загруженного файла '%value%' определить невозможно",
    Zend_Validate_File_ImageSize::NOT_READABLE => "Рисунок '%value%' невозможно считать",
    Zend_Validate_File_FilesSize::TOO_BIG => "Размеры загруженных файлов в сумме имеют размер больше разрешенного",
    Zend_Validate_File_FilesSize::TOO_SMALL => "Размеры загруженных файлов в сумме имеют слишком маленький объем",
    Zend_Validate_File_FilesSize::NOT_READABLE => "Один или несколько файлов неудается считать",
    Zend_Validate_File_Count::TOO_MANY => "Слишком много файлов загружено, разрешено только '%value%'",
    Zend_Validate_File_Count::TOO_FEW => "Слишком мало файлов загружено, минимальное допустимое число файлов '%value%'",
    Zend_Validate_File_Extension::FALSE_EXTENSION => "Загруженый файл '%value%' имеет неразрешенное расширение",
    Zend_Validate_File_Extension::NOT_FOUND => "Файл '%value%' не найден"
);