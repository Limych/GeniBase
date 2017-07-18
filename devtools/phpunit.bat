@ECHO OFF
cd ..
SET BIN_TARGET=vendor\bin\%~n0.bat
SET LOG_TARGET=build\logs\%~n0.log
SET OPTS=-vv --colors=auto %*

call ansicon "%BIN_TARGET%" %OPTS%

echo.
pause
