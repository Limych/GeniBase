-- Source: https://habrahabr.ru/post/179157/
 
-- Пример использования:
-- 
-- Допустим, что есть таблица, в которой хранятся географические данные.
-- Таблица имеет такую структуру:
-- 
-- CREATE TABLE geo (
--          id INT,
--          name VARCHAR(100),
--          x DECIMAL(9,6),
--          y DECIMAL (9,6)
-- );
-- 
-- тогда, для получения всех населенных пунктов, расположенных на
-- расстоянии 200 километров от исходной точки понадобится сделать следующее:
-- в переменную src сохраняем координаты нужной точки в виде объекта POINT
-- (системная функция Point() создает объект типа POINT из двух строковых координат)
-- 
-- SELECT @src := Point(x,y) FROM geo WHERE name = 'Москва';
-- -- формируем "область поиска" с заданным радиусом
-- CALL geobox_pt(@src, 200.0, @top_lft, @bot_rgt);
-- -- достаем данные 
-- SELECT g.name, geodist(X(@src), Y(@src), x, y) AS dist
-- FROM geo g
-- WHERE x BETWEEN X(@bot_rgt) AND X(@top_lft)
-- AND y BETWEEN Y(@top_lft) AND Y(@bot_rgt)
-- HAVING dist < 200.0
-- ORDER BY dist desc;


-- geodist() определяет расстояние между точками по их координатам.
-- 
-- число 6371 - это радиус Земли в километрах, для использования решения в других
-- единицах измерения, достаточно сконвертировать радиус Земли в нужную единицу измерения
DROP FUNCTION IF EXISTS geodist;
DELIMITER $$
CREATE FUNCTION geodist (
	src_lat DECIMAL(9,6), src_lon DECIMAL(9,6),
	dst_lat DECIMAL(9,6), dst_lon DECIMAL(9,6)
) RETURNS DECIMAL(6,2) DETERMINISTIC
COMMENT 'Calculate distance between two geolocation points'
BEGIN
	SET @dist := 6371 * 2 * ASIN(SQRT(
		POWER(SIN((src_lat - ABS(dst_lat)) * PI()/180 / 2), 2) +
		COS(src_lat * PI()/180) *
		COS(ABS(dst_lat) * PI()/180) *
		POWER(SIN((src_lon - dst_lon) * PI()/180 / 2), 2)
	));
	RETURN @dist;
END $$
DELIMITER ;

-- geodist_pt() является оберткой для geodist(), и работает с координатами точек в
-- виде объекта типа POINT.
DROP FUNCTION IF EXISTS geodist_pt;
DELIMITER $$
CREATE FUNCTION geodist_pt (src POINT, dst POINT) 
RETURNS DECIMAL(6,2) DETERMINISTIC
COMMENT 'Calculate distance between two geolocation points (POINT datatype version)'
BEGIN
	RETURN geodist(X(src), Y(src), X(dst), Y(dst));
END $$
DELIMITER ;

-- geobox() вычисляет координаты области поиска
-- 
-- src_lat, src_lon -> центральная точка области поиска
-- dist -> расстояние от центра в километрах
-- lat_top, lon_lft -> координаты верхнего левого угла области поиска
-- lat_bot, lon_rgt -> координаты нижнего правого угла области поиска
DROP PROCEDURE IF EXISTS geobox;
DELIMITER $$
CREATE PROCEDURE geobox (
	IN src_lat DECIMAL(9,6), IN src_lon DECIMAL(9,6), IN dist DECIMAL(6,2),
	OUT lat_top DECIMAL(9,6), OUT lon_lft DECIMAL(9,6),
	OUT lat_bot DECIMAL(9,6), OUT lon_rgt DECIMAL(9,6)
) DETERMINISTIC
COMMENT 'Calculate coordinates of search area around geolocation point'
BEGIN
	SET lat_top := src_lat + (dist / 69);
	SET lon_lft := src_lon - (dist / ABS(COS(RADIANS(src_lat)) * 69));
	SET lat_bot := src_lat - (dist / 69);
	SET lon_rgt := src_lon + (dist / ABS(COS(RADIANS(src_lat)) * 69));
END $$
DELIMITER ;

-- geobox_pt() вычисляет координаты верхнего левого и нижнего правого угла области
-- поиска с помощью процедуры geobox(), затем конвертирует полученные координаты в
-- объекты типа POINT.
-- 
-- pt -> центральная точка области поиска
-- dist -> расстояние от центра в километрах
-- top_lft -> верхний левый угол области поиска (объект типа POINT)
-- bot_rgt -> нижний правый угол области поиска (объект типа POINT)
DROP PROCEDURE IF EXISTS geobox_pt;
DELIMITER $$
CREATE PROCEDURE geobox_pt (
	IN pt POINT, IN dist DECIMAL(6,2),
	OUT top_lft POINT, OUT bot_rgt POINT
) DETERMINISTIC
COMMENT 'Calculate coordinates of search area around geolocation point (POINT datatype version)'
BEGIN
	CALL geobox(X(pt), Y(pt), dist, @lat_top, @lon_lft, @lat_bot, @lon_rgt);
	SET top_lft := POINT(@lat_top, @lon_lft);
	SET bot_rgt := POINT(@lat_bot, @lon_rgt);
END $$
DELIMITER ;
