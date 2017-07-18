@ECHO OFF
cd ..
SET BIN_TARGET=vendor\bin\%~n0.bat
SET OPTS=%*

call "%BIN_TARGET%" %OPTS%

echo.
pause
