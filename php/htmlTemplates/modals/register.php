<!-- register user modal -->
<div class="modal" id="registerUserModal" tabindex="-1" role="dialog" aria-labelledby="registerModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button"class="close" data-dismiss="modal" aria-label="<?=_('Close')?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="registerModalLabel"><?=_('Register')?></h4>
            </div>
            <div class="modal-body">
                <form action="user/register" data-send-action="ajax" method="post">
                    <div class="form-group">
                        <label for="firstNameRegister"><?=_('First name')?></label>
                        <input id="firstNameRegister"
                               class="form-control"
                               type="text"
                               name="firstName"
                               placeholder="<?=_('First name')?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="lastNameRegister"><?=_('Last name')?></label>
                        <input id="lastNameRegister"
                               class="form-control"
                               type="text"
                               name="lastName"
                               placeholder="<?=_('Last name')?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="pseudonymRegister"><?=_('Pseudonym')?></label>
                        <input id="pseudonymRegister"
                               class="form-control"
                               type="text"
                               name="pseudonym"
                               placeholder="<?=_('Pseudonym')?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="emailRegister"><?=_('Email')?></label>
                        <input id="emailRegister"
                               class="form-control"
                               type="email"
                               name="email"
                               placeholder="<?=_('Email')?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="passwordRegister"><?=_('Password')?></label>
                        <input id ="passwordRegister"
                               class="form-control"
                               type="password"
                               name="password"
                               placeholder="<?=_('Password')?>"
                        >
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-default" value="<?=_('Register')?>">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
