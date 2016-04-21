<!-- alert to display user choice input on kick / ban events -->
<div id="alert-input-choice" class="alert alert-info">
    <p><?=_('Complete the reason')?></p>
    <form data-send-action="jsCallback" data-callback-name="setReasonCallbackEvent">
        <div class="form-group">
            <textarea class="reason form-control"
                      rows="3"
                      name="reason"
                      placeholder="<?=_('Reason')?>"
            ></textarea>
        </div>
        <button class="send btn btn-default" type="submit"><?=_('OK')?></button>
    </form>
</div>
