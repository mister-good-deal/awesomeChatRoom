<!-- Top menu -->
<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
    <div class="container-fluid">
        <button type="button"
                class="navbar-toggle navbar-toggle-left"
                data-toggle="offcanvas"
                data-target=".navmenu"
        >
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>

        <div class="pull-right">
            <button type="button"
                    class="btn btn-primary navbar-btn"
                    data-toggle="modal"
                    data-target="#registerUserModal"
            >
                <?=_('Register')?>
            </button>

            <button type="button"
                    class="btn btn-primary navbar-btn"
                    data-toggle="modal"
                    data-target="#connectUserModal"
            >
                <?=_('Connect')?>
            </button>
        </div>
    </div>
</nav>
