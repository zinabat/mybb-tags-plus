import { autocomplete } from "./autocomplete.js";

export const tags = (tags, badgeTemplateNode) => {
    const insertedTags = new Map();

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.querySelector('#thread-tags-input');
        autocomplete(input, tags.map(x => x.name), (text) => dispatchCommit(input, text));
        input.addEventListener('keydown', onTextKeyDown);
        input.addEventListener('tagsplus-commit', onTextCommit);
        document.querySelectorAll('#thread-tag-badges .close')
            .forEach((button) => {
                const name = button.getAttribute('data-tagname');
                insertedTags.set(name, tags.find(x => x.name === name));
                button.addEventListener('click', onRemoveClick);
            });
    });

    const dispatchCommit = (elem, tagName) => {
        const event = new CustomEvent('tagsplus-commit', {
            detail: {
                tagName: tagName
            }
        });
        elem.dispatchEvent(event);
    };

    const onTextKeyDown = (e) => {
        switch (e.code) {
            case 'Comma':
                const value = e.target.value;
                e.preventDefault();
                e.target.value = value.substr(0, value.length - 1);
                dispatchCommit(e.target, e.target.value);
                break;
            case 'Enter':
                e.stopPropogation();
                e.preventDefault();
                return false;
        }
    };

    const setTagIds = () => {
        document.querySelector('[name="thread-tag-ids"]')
            .setAttribute('value',
                Array.from(insertedTags.values()).map(x => x.tagid).join(',')
            );
    };

    const onRemoveClick = (e) => {
        const tagName = e.target.parentElement.getAttribute('data-tagname');
        insertedTags.delete(tagName);
        setTagIds();
        e.target.closest('.tagsplus-badge').remove();
    };

    const onTextCommit = (e) => {
        const selectedTag = tags.find(x => x.name === e.detail.tagName);
        if (!selectedTag) return;
        // if it's not a duplicate, add the id to the field
        if (!insertedTags.has(selectedTag.name)) {
            insertedTags.set(selectedTag.name, selectedTag);
            setTagIds();
            const newBadge = badgeTemplateNode.cloneNode(true);
            newBadge.classList.add(`tagsplus-id-${selectedTag.tagid}`);
            newBadge.classList.remove('tagsplus-id-ID');
            newBadge.innerHTML = newBadge.innerHTML.replaceAll('NAME', selectedTag.name);
            newBadge.querySelector('.close').addEventListener('click', onRemoveClick);
            document.querySelector('#thread-tag-badges').append(newBadge);
        }
        // clear the input
        e.target.value = '';
        e.target.setAttribute('value', '');
    };
};