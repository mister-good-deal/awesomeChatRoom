<!-- admin chat modal -->
<div class="modal chat-admin"
     id="chat-admin-sample"
     tabindex="-1"
     role="dialog"
     aria-labelledby="adminChatModalLabel"
     data-room-name=""
     data-room-password=""
>
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 id="adminChatModalLabel" class="modal-title">
                    <?=_('Chat administration')?> "<span class="room-name"></span>"
                </h4>
            </div>
            <div class="modal-body">
                <form class="form-inline"
                      data-send-action="jsCallback"
                      data-callback-name="setRoomInfosCallbackEvent"
                >
                    <div class="form-group">
                        <label><?=_('Room name')?></label>
                        <input class="room-name" type="text" name="roomName" value="">
                    </div>
                    <div class="form-group">
                        <label><?=_('Room password')?></label>
                        <input class="room-password" type="password" name="roomPassword" value="">
                    </div>
                    <button class="send btn btn-default" type="submit"><?=_('Change')?></button>
                </form>
                <div class="panel panel-default">
                    <div class="panel-heading"><?=_('Users list')?></div>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?=_('User pseudonym')?></th>
                                <th colspan="3"><?=_('Actions')?></th>
                            </tr>
                        </thead>
                        <tbody class="users-list">
                            <tr class="hide sample" data-pseudonym="">
                                <td class="user-pseudonym">Pseudonym</td>
                                <td>
                                    <button class="btn btn-default kick" type="button"><?=_('Kick')?></button>
                                </td>
                                <td>
                                    <button class="btn btn-default ban" type="button"><?=_('Ban')?></button>
                                </td>
                                <td>
                                    <button class="toggle-rights btn btn-default" type="button" data-refer="">
                                        <?=_('Rights')?> <span class="caret"></span>
                                    </button>
                                </td>
                            </tr>
                            <tr class="hide sample right" data-pseudonym="">
                                <td colspan="4" class="warper">
                                    <label><?=_('Kick users')?></label>
                                    <input type="checkbox"
                                           name="kick"
                                           data-on-color="success"
                                           data-off-color="danger"
                                           data-size="mini"
                                    >
                                </td>
                            </tr>
                            <tr class="hide sample right" data-pseudonym="">
                                <td colspan="4" class="warper">
                                    <label><?=_('Ban users')?></label>
                                    <input type="checkbox"
                                           name="ban"
                                           data-on-color="success"
                                           data-off-color="danger"
                                           data-size="mini"
                                    >
                                </td>
                            </tr>
                            <tr class="hide sample right" data-pseudonym="">
                                <td colspan="4" class="warper">
                                    <label><?=_('Grant users rights')?></label>
                                    <input type="checkbox"
                                           name="grant"
                                           data-on-color="success"
                                           data-off-color="danger"
                                           data-size="mini"
                                    >
                                </td>
                            </tr>
                            <tr class="hide sample right" data-pseudonym="">
                                <td colspan="4" class="warper">
                                    <label><?=_('Edit room information')?></label>
                                    <input type="checkbox"
                                           name="edit"
                                           data-on-color="success"
                                           data-off-color="danger"
                                           data-size="mini"
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="panel panel-default">
                    <div class="panel-heading"><?=_('Users banned')?></div>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?=_('Ip')?></th>
                                <th><?=_('Pseudonym')?></th>
                                <th><?=_('Banned by')?></th>
                                <th><?=_('Reason')?></th>
                                <th><?=_('At')?></th>
                                <th><?=_('Actions')?></th>
                            </tr>
                        </thead>
                        <tbody class="banned-list">
                            <tr class="hide sample">
                                <td class="ip">0.0.0.0</td>
                                <td class="pseudonym-banned">pseudonym used</td>
                                <td class="pseudonym-admin">admin pseudonym</td>
                                <td class="reason">reason</td>
                                <td class="date">01/01/2000</td>
                                <td>
                                    <button class="btn btn-default unbanned" type="button">
                                        <?=_('unbanned')?>
                                    </button>
                                </td>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
