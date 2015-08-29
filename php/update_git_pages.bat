@echo.
@echo **** Auto update phpdoc in git-hub ****
@echo.
call phpdoc
@echo.
@echo **** phpDoc generated ****
@echo.
cd ../../web-doc/phpDoc
call git add .
call git commit -a -m "update phpDoc"
call git push origin gh-pages
@echo.
@echo **** phpDoc pushed in git-hub pages branch ****
@echo.
cd ../../web/php
