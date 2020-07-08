<h1>Все офферы от API</h1>
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Логотип</th>
            <th>Название</th>
            <th>Описание</th>
            <th>Условие площадки</th>
            <th>Описание</th>
            <th>Изменен</th>
            <th>Переменные</th>
        </tr>
    </thead>
    <tbody>
        <? foreach ( $offersData as $key => $offer ): ?>
            <tr>
                <td><?= $offer->id ?></td>
                <td><img src="<?= $offer->logo ?>" width="150"></td>
                <td><?= $offer->name ?></td>
                <td><?= $offer->name ?></td>
                <td><?= $offer->site_condition  ?></td>
                <td>
                    <div class="offer-details">
                        <a href="javascript:void(0)" class="offer-details__toggle">Подробности</a>
                        <div class="offer-details__data" style="display: none;"><?= $offer->description  ?></div>
                    </div>
                </td>
                <td style="white-space: nowrap"><?= $offer->modified  ?></td>
                <td>
                    <?
                        $objectVars = get_object_vars($offer);
                        foreach ($objectVars as $key => $item) {
                            ?>
                                <div class="object-var">
                                    <a href="javascript:void(0)" class="object-var__key"><?= $key ?></a>
                                    <div class="object-var__data" style="display: none"><? debug($item) ?></div>
                                </div>
                            <?
                        }
                    ?>
                </td>
            </tr>
        <? endforeach ?>
    </tbody>
</table>
<div id="myModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Modal title</h4>
            </div>
            <div class="modal-body">
                <p>One fine body&hellip;</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<script>
    var modal = $("#myModal");
    $('.object-var__key').click(function() {
        var content = $(this).next().html();
        modal.find('.modal-body').html(content);
        modal.modal();
    });

    $('.offer-details__toggle').click(function() {
        var content = $(this).next().html();
        modal.find('.modal-body').html(content);
        modal.modal();
    });
</script>