@echo.
@echo **** Auto update jsDoc in git-hub ****
@echo.
call .\node_modules\.bin\jsdoc
@echo.
@echo **** jsDoc generated ****
@echo.
cd ../../../web-doc/jsDoc
call git add --all .
call git commit -a -m "update jsDoc"
call git push origin gh-pages
@echo.
@echo **** jsDoc pushed in git-hub pages branch ****
@echo.
cd ../../web/static/js