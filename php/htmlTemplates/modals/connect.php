<!-- connect user modal -->
<div class="modal" id="connectUserModal" tabindex="-1" role="dialog" aria-labelledby="connectModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button"class="close" data-dismiss="modal" aria-label="<?=_('Close')?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="connectModalLabel"><?=_('Connect')?></h4>
            </div>
            <div class="modal-body">
                <!-- connect user -->
                <form action="user/connect" data-send-action="ajax" method="post">
                    <div class="form-group">
                        <label for="loginConnect"><?=_('First name')?></label>
                        <input id="loginConnect"
                               class="form-control"
                               type="text"
                               name="login"
                               placeholder="<?=_('Login (Pseudonym or email')?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="loginPassword"><?=_('Password')?></label>
                        <input id="loginPassword"
                               class="form-control"
                               type="password"
                               name="password"
                               placeholder="<?=_('Password')?>"
                        >
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-default" value="<?=_('Connect')?>">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
