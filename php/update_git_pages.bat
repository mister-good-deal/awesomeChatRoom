@echo.
@echo **** Auto update phpdoc in git-hub ****
@echo.
call phpdoc
@echo.
@echo **** phpDoc generated ****
@echo.
cd ../utilities-gh-pages
call git add .
call git commit -a -m "update phpDoc"
call git push
@echo.
@echo **** phpDoc pushed in git-hub pages branch ****
@echo.
cd ../utilities
