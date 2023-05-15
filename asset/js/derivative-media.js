$(function () {

    $('a.derivative-media.on-demand:not(.demand-confirmed)').on('click', (e) => {
        const link = $(e.target);
        const url = link.data('url');
        const size = link.data('size');
        const href = link.attr('href');

        if (href && href.length && href !== '#') {
            return;
        }

        e.stopPropagation();
        e.preventDefault();

        const derivativeList = $(link).closest('.derivative-list');

        // The size may be unknown in case of a unbuild file.
        const textWarn = size
            ? (derivativeList.data('text-warn-size') ? derivativeList.data('text-warn-size').replace('{size}', size) : `Are you sure to download the file (${size} bytes)?`)
            : (derivativeList.data('text-warn') ? derivativeList.data('text-warn') : 'Are you sure to download this file?');
        const textNo = derivativeList.data('text-no') ? derivativeList.data('text-no') : 'No';
        const textYes = derivativeList.data('text-yes') ? derivativeList.data('text-yes') : 'Yes';
        const textQueued = derivativeList.data('text-queued') ? derivativeList.data('text-queued') : 'The file is in queue. Reload the page later.';
        const textOk = derivativeList.data('text-ok') ? derivativeList.data('text-ok') : 'Ok';

        const html=`
<dialog id="derivative-on-demand">
    <form id="derivative-form" method="dialog" action="#">
        <p>${textWarn}</p>
        <div class="drivative-actions" style="display: flex; justify-content: space-evenly;">
            <button type="cancel" id="derivative-no"  class="derivative-no" value="no" formmethod="dialog">${textNo}</button>
            <button type="button" id="derivative-yes" class="derivative-yes" value="yes" formmethod="dialog">${textYes}</button>
        </div>
    </form>
</dialog>
<dialog id="derivative-queued">
    <form id="derivative-form-queud" method="dialog" action="#">
        <p>${textQueued}</p>
        <div class="drivative-actions" style="display: flex; justify-content: space-evenly;">
            <button type="button" id="derivative-ok" class="derivative-ok" value="yes" formmethod="dialog">${textOk}</button>
        </div>
    </form>
</dialog>
`;

        if ($('#derivative-on-demand').length) {
           $('#derivative-on-demand').remove();
        }

        $('body').append(html);

        link.attr('href', '#');

        const dialog = document.getElementById('derivative-on-demand');
        const no = document.getElementById('derivative-no');
        const yes = document.getElementById('derivative-yes');

        dialog.showModal();

        no.addEventListener('click', () => {
            dialog.close();
            e.preventDefault();
            link.attr('href', '#');
            return false;
        });

        yes.addEventListener('click', () => {
            dialog.close();
            // link.attr('href', url);
            // link.addClass('demand-confirmed');
            // Js forbids a click to a link, so send via ajax. Anyway, the
            // response should not be sent. Use argument "prepare" to avoid
            // to send response immediatly.
            $.get(url, {prepare: 1})
                .fail(function() {
                    // The fail is normal for now.
                    const dialogQueued = document.getElementById('derivative-queued');
                    const ok = document.getElementById('derivative-ok');
                    dialogQueued.showModal();
                    ok.addEventListener('click', () => {
                        dialogQueued.close();
                    });
                })
            ;
            return true;
        });

    });

});
