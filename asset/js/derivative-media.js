$(function () {

    $('a.derivative-media.on-demand').on('click', (e) => {
        e.stopPropagation();

        if ($('#derivative-form').length) {
           $('#derivative-form').remove();
        }

        const link = $(this);
        const url = link.data('url');
        const size = link.data('size');

        // The size may be unknown in case of a unbuild file.
        const textWarn = size
            ? `Are you sure to download the file (${size} bytes)?`
            : `Are you sure to download the file?`;
        const textNo = `No`;
        const textYes = `Yes`;

        const html=`
<dialog id="derivative-on-demand">
    <form id="derivative-form" method="dialog" action="#">
        <p>${textWarn}</p>
        <div>
            <button type="cancel" id="derivative-no" value="no" formmethod="dialog">${textNo}</button>
            <button type="button" id="derivative-yes" value="yes" formmethod="dialog>${textYes}</button>
        </div>
    </form>
</dialog>`;

        $('body').append(html);

        const dialog = document.getElementById('derivative-on-demand');
        const no = dialog.getElementById('derivative-no');
        const yes = dialog.getElementById('derivative-yes');

        dialog.showModal();

        no.addEventListener('click', () => {
            link.prop('href', '#');
            dialog.close(false);
            e.preventDefault();
        });

        yes.addEventListener('click', () => {
            link.prop('href', url);
            dialog.close(true);
        });

    });

});
