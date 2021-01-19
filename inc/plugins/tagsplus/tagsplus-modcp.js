$(() => {
    $('#add-new-tag button').click(onNewTagBtnClick);
    $('.tag-toggle-enable').click(onEnableToggleClick);
    $('.tag-delete').click(onDeleteClick);
});
const ajaxError = (res, status, err) => {
    userError(res.responseText);
    console.error(res, status, err);
};
const userError = (msg) => {
    const errorBox = $('#message span');
    if (errorBox.length) {
        errorBox.html(msg);
    } else {
        $('#tag-management-table').before(`<p id="message" class="error"><span class="text">${msg}</span></p>`);
    }
};

const onNewTagBtnClick = async (e) => {
    e.preventDefault();
    // get the name
    const name = $('input[name="new-tag-name"]').val();
    if (name === '') {
        return userError('Tag names cannot be blank.');
    }
    //get the slug
    const slug = $('input[name="new-tag-slug"]').val();
    // send the request
    $.ajax({
        method: 'POST',
        url: 'xmlhttp.php?action=tagsplus&do=create',
        dataType: 'json',
        data: {
            ajax: 1,
            name: name,
            slug: slug
        }
    }).done((res) => {
        const $noRows = $('#tag-no-rows');
        let $newRow = null;
        if ($noRows.length) {
            $noRows.before(unescape(res));
            $newRow = $noRows.prev();
            $noRows.remove();
        } else {
            const $firstRow = $('tr[data-tagid]').first();
            $firstRow.before(unescape(res));
            $newRow = $firstRow.prev();
        }
        $newRow.find('.tag-toggle-enable').click(onEnableToggleClick);
        $newRow.find('.tag-delete').click(onDeleteClick);
    }).fail(ajaxError);
};

const onEnableToggleClick = (e) => {
    // send the enable/disable request
    $.ajax({
        method: 'POST',
        url: 'xmlhttp.php?action=tagsplus&do=toggle',
        dataType: 'json',
        data: {
            ajax: 1,
            tagid: $(e.target).data('tagid')
        }
    }).done((res) => {
        // on success, change the verbage
        const verb = $(e.target).html();
        if (verb.toLowerCase().trim() === 'disable') {
            $(e.target).html('Enable');
        } else {
            $(e.target).html('Disable');
        }
    }).fail(ajaxError);
};

const onDeleteClick = (e) => {
    // confirm that this is really what they want to do
    const msg = 'Deleting will remove the tag from all posts it is attached to. Are you sure you want to delete?';
    if (!confirm(msg)) return;
    // send the delete request
    $.ajax({
        method: 'POST',
        url: 'xmlhttp.php?action=tagsplus&do=delete',
        dataType: 'json',
        data: {
            ajax: 1,
            tagid: $(e.target).data('tagid')
        }
    }).done((res) => {
        // on success, remove the row
        $(e.target).closest('tr').remove();
    }).fail(ajaxError);
};

const onRenameClick = (e) => {
    // show the input
};

const onRenameConfirm = (e) => {
    // confirm first
    const msg = 'Renaming will change the tag on all posts it is attached to. Are you sure you want to rename?';
    if (!confirm(msg)) return;
    // send rename request
    // hide the input, change the name on the front end
};