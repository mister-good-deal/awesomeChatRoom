@echo.
@echo **** Auto update jsDoc and phpDoc in git-hub ****
@echo.
cd php
call ./update_php_doc.bat
cd ../static/js
call ./update_js_doc.bat
cd ../..
@echo.
@echo **** jsDoc and phpDoc generated ****
@echo.