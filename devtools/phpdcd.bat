@ECHO OFF
cd ..
SET BIN_TARGET=vendor\bin\%~n0.bat
SET LOG_TARGET=build\logs\%~n0.log
SET OPTS=%*

call "%BIN_TARGET%" app src tests\phpunit\tests %OPTS% >"%LOG_TARGET%"

type "%LOG_TARGET%"
echo.
pause
