<?php
/**
 * GeniBase
 *
 * Copyright (c) 2017 by Andrey Khrolenok <andrey@khrolenok.ru>
 *
 * This file is part of some open source application.
 *
 * Some open source application is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * Some open source application is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license     GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 * @copyright   Copyright (c) 2017 by Andrey Khrolenok <andrey@khrolenok.ru>
 *
 * @package     GeniBase
 */

$allow_dev_mode = true;
// $allow_dev_mode = false;

define('BASE_DIR', dirname(__DIR__));

include BASE_DIR.'/app/app.php';
