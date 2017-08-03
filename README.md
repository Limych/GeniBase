GeniBase
==================

[![Build Status](https://travis-ci.org/Limych/GeniBase.svg?branch=3.0.x-dev)](https://travis-ci.org/Limych/GeniBase)
[![Dependency Status](https://www.versioneye.com/user/projects/596db2a5368b0800554f1c2f/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/596db2a5368b0800554f1c2f)
[![Coverage Status](https://coveralls.io/repos/github/Limych/GeniBase/badge.svg?branch=3.0.x-dev)](https://coveralls.io/github/Limych/GeniBase?branch=3.0.x-dev)

## Documentation

Auto-generated technical documentation is available at the https://limych.github.io/GeniBase/

## Инсталляция для разработчиков

Для работы проекта необходимо:

* PHP версии 5.5 или выше;
* MySQL (рекомендуется версия 5.5 или выше);
* Композер (Если у вас нет Композера, вы его всегда можете скачать по адресу https://getcomposer.org/composer.phar и разместить в корне проекта, либо там же просто выполнить команду `wget https://getcomposer.org/composer.phar`.)

После копирования структуры проекта в его корневой папке необходимо запустить Композер:

    php composer.phar install

Композер автоматически скачает из интернета и установит все необходимые библиотеки.

После этого для запуска сайта необходимо сделать каталог `web/` корнем сайта. Либо перенести содержимое этого каталога в нужное место и настроить в файле `index.php` значение константы `BASE_DIR` на корень проекта.

Далее необходимо создать MySQL базу данных.
Структура базы описана в файле `assets/dbase/db_schema.sql` — его достаточно просто отправить в MySQL.

А теперь в каталоге `app/configs/` сделайте копии файлов настроек и заполните их необходимымы данными для доступа к СУБД:

* `prod.php.dist` → `prod.php` (основные настройки);
* `dev.php.dist` → `dev.php` (отладочные настройки; применяются поверх основных).

Всё. Система развёрнута. Можно заходить на созданный сайт.

## Screenshots

![Географическое место](https://github.com/Limych/GeniBase/raw/3.0.x-dev/assets/screenshots/place.png)