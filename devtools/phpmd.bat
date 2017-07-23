@ECHO OFF
cd ..
SET BIN_TARGET=vendor\bin\%~n0.bat
SET LOG_TARGET=build\logs\%~n0.log
SET OPTS=text build/config/phpmd.xml %*
del "%LOG_TARGET%"

call "%BIN_TARGET%" app %OPTS% >>"%LOG_TARGET%"
call "%BIN_TARGET%" src %OPTS% >>"%LOG_TARGET%"
call "%BIN_TARGET%" tests\phpunit\tests %OPTS% >>"%LOG_TARGET%"

type "%LOG_TARGET%"
echo.
rem pause
