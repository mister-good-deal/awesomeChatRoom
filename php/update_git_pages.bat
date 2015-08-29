@echo.
@echo **** Auto update phpdoc in git-hub ****
@echo.
call phpdoc
@echo.
@echo **** phpDoc generated ****
@echo.
cd ../doc/PHP
call git add .
call git commit -a -m "update phpDoc"
call git push
@echo.
@echo **** phpDoc pushed in git-hub pages branch ****
@echo.
cd ../php
