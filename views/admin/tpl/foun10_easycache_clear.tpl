[{include file="headitem.tpl" title="foun10 EasyCache"}]

<style>
    .foun10ec-wrap { padding: 4px 14px 20px; }
    .foun10ec-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .foun10ec-header h1 { margin: 0; }
    .foun10ec-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .03em; }
    .foun10ec-badge-on { background: #dff0d8; color: #3c763d; border: 1px solid #b2d8a8; }
    .foun10ec-badge-off { background: #f2f2f2; color: #777; border: 1px solid #ddd; }
    .foun10ec-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 14px 16px; margin-bottom: 16px; }
    .foun10ec-card h2 { font-size: 14px; margin: 0 0 10px; text-transform: uppercase; letter-spacing: .03em; color: #555; }
    .foun10ec-hint { color: #777; font-size: 12px; margin: 2px 0 10px; }
    .foun10ec-actions { margin-top: 12px; }
    .foun10ec-msg { padding: 8px 12px; border-radius: 3px; margin-bottom: 14px; }
    .foun10ec-msg-success { background: #dff0d8; color: #3c763d; border: 1px solid #b2d8a8; }
    .foun10ec-search-row { display: flex; gap: 8px; margin-bottom: 8px; }
    .foun10ec-search-row select { flex: 0 0 170px; }
    .foun10ec-search-row input[type="text"] { flex: 1; }
    .foun10ec-search-results { border: 1px solid #ddd; border-radius: 3px; max-height: 220px; overflow-y: auto; margin: 0 0 10px; padding: 0; display: none; }
    .foun10ec-search-results li { list-style: none; padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #eee; font-size: 13px; }
    .foun10ec-search-results li:last-child { border-bottom: none; }
    .foun10ec-search-results li:hover { background: #f5f8ff; }
    .foun10ec-search-empty { padding: 8px 10px; color: #888; font-style: italic; font-size: 13px; cursor: default; }
    .foun10ec-search-empty:hover { background: none; }
    .foun10ec-selected { font-size: 13px; margin-bottom: 10px; min-height: 18px; }
</style>

<div class="foun10ec-wrap">
    <div class="foun10ec-header">
        <h1>[{oxmultilang ident="FOUN10_EASYCACHE"}]</h1>
        [{if $easyCacheEnabled}]
            <span class="foun10ec-badge foun10ec-badge-on">[{oxmultilang ident="EC_STATUS_ENABLED"}]</span>
        [{else}]
            <span class="foun10ec-badge foun10ec-badge-off">[{oxmultilang ident="EC_STATUS_DISABLED"}]</span>
        [{/if}]
    </div>

    [{if $easyCacheLastAction == "clearcache"}]
        <div class="foun10ec-msg foun10ec-msg-success">[{oxmultilang ident="EC_MSG_CACHE_CLEARED"}] ([{$easyCacheClearedCount}] [{oxmultilang ident="EC_MSG_CACHE_CLEARED_FILES"}])</div>
    [{elseif ($easyCacheLastAction == "clearstart" || $easyCacheLastAction == "cleartag") && $easyCacheClearedTag}]
        <div class="foun10ec-msg foun10ec-msg-success">[{oxmultilang ident="EC_MSG_TAG_CLEARED"}] &bdquo;[{$easyCacheClearedTag}]&ldquo; ([{$easyCacheClearedCount}] [{oxmultilang ident="EC_MSG_CACHE_CLEARED_FILES"}])</div>
    [{/if}]

    <div class="foun10ec-card">
        <h2>[{oxmultilang ident="EC_SECTION_CLEAR_ALL"}]</h2>
        <p class="foun10ec-hint">[{oxmultilang ident="EC_HINT_CLEAR_CACHE"}]</p>
        <form action="[{$oViewConf->getSelfLink()}]" method="post" onsubmit="return confirm('[{oxmultilang ident="EC_CONFIRM_CLEAR_CACHE"}]');">
            [{$oViewConf->getHiddenSid()}]
            <input type="hidden" name="cl" value="[{$oViewConf->getActiveClassName()}]">
            <input type="hidden" name="fnc" value="clearcache">
            <button type="submit" class="btn btn-default">[{oxmultilang ident="EC_BUTTON_CLEAR_CACHE"}]</button>
        </form>
    </div>

    <div class="foun10ec-card">
        <h2>[{oxmultilang ident="EC_SECTION_CLEAR_START"}]</h2>
        <p class="foun10ec-hint">[{oxmultilang ident="EC_HINT_CLEAR_START"}]</p>
        <form action="[{$oViewConf->getSelfLink()}]" method="post">
            [{$oViewConf->getHiddenSid()}]
            <input type="hidden" name="cl" value="[{$oViewConf->getActiveClassName()}]">
            <input type="hidden" name="fnc" value="clearstart">
            <button type="submit" class="btn btn-default">[{oxmultilang ident="EC_BUTTON_CLEAR_START"}]</button>
        </form>
    </div>

    <div class="foun10ec-card">
        <h2>[{oxmultilang ident="EC_SECTION_CLEAR_TAG"}]</h2>
        <p class="foun10ec-hint">[{oxmultilang ident="EC_HINT_CLEAR_TAG"}]</p>

        <div class="foun10ec-search-row">
            <select id="foun10ec-tagtype">
                <option value="product" data-placeholder="[{oxmultilang ident="EC_SEARCH_PLACEHOLDER_PRODUCT"}]">[{oxmultilang ident="EC_TAGTYPE_PRODUCT"}]</option>
                <option value="category" data-placeholder="[{oxmultilang ident="EC_SEARCH_PLACEHOLDER_CATEGORY"}]">[{oxmultilang ident="EC_TAGTYPE_CATEGORY"}]</option>
                <option value="manufacturer" data-placeholder="[{oxmultilang ident="EC_SEARCH_PLACEHOLDER_MANUFACTURER"}]">[{oxmultilang ident="EC_TAGTYPE_MANUFACTURER"}]</option>
            </select>
            <input type="text" id="foun10ec-search-term" placeholder="[{oxmultilang ident="EC_SEARCH_PLACEHOLDER_PRODUCT"}]" autocomplete="off">
        </div>

        <ul class="foun10ec-search-results" id="foun10ec-search-results"></ul>

        <form action="[{$oViewConf->getSelfLink()}]" method="post" id="foun10ec-cleartag-form">
            [{$oViewConf->getHiddenSid()}]
            <input type="hidden" name="cl" value="[{$oViewConf->getActiveClassName()}]">
            <input type="hidden" name="fnc" value="cleartag">
            <input type="hidden" name="tagtype" id="foun10ec-selected-type">
            <input type="hidden" name="tagid" id="foun10ec-selected-id">

            <div class="foun10ec-selected" id="foun10ec-selected-label"></div>

            <div class="foun10ec-actions">
                <button type="submit" class="btn btn-default" id="foun10ec-cleartag-submit" disabled>[{oxmultilang ident="EC_BUTTON_CLEAR_TAG"}]</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var baseUrl = '[{$oViewConf->getSelfLink()|replace:"&amp;":"&"}]&cl=[{$oViewConf->getActiveClassName()}]';
    var typeSelect = document.getElementById('foun10ec-tagtype');
    var termInput = document.getElementById('foun10ec-search-term');
    var resultsList = document.getElementById('foun10ec-search-results');
    var selectedType = document.getElementById('foun10ec-selected-type');
    var selectedId = document.getElementById('foun10ec-selected-id');
    var selectedLabel = document.getElementById('foun10ec-selected-label');
    var submitButton = document.getElementById('foun10ec-cleartag-submit');

    var searchTimeout = null;
    var currentRequestToken = 0;

    function clearSelection() {
        selectedType.value = '';
        selectedId.value = '';
        selectedLabel.textContent = '';
        submitButton.disabled = true;
    }

    function hideResults() {
        resultsList.style.display = 'none';
        resultsList.innerHTML = '';
    }

    function renderResults(items) {
        resultsList.innerHTML = '';

        if (!items.length) {
            var empty = document.createElement('li');
            empty.className = 'foun10ec-search-empty';
            empty.textContent = '[{oxmultilang ident="EC_SEARCH_EMPTY"}]';
            resultsList.appendChild(empty);
            resultsList.style.display = 'block';
            return;
        }

        items.forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = item.label;
            li.addEventListener('click', function () {
                selectedType.value = typeSelect.value;
                selectedId.value = item.id;
                selectedLabel.textContent = '[{oxmultilang ident="EC_SELECTED_LABEL"}]: ' + item.label;
                submitButton.disabled = false;
                hideResults();
            });
            resultsList.appendChild(li);
        });

        resultsList.style.display = 'block';
    }

    function runSearch() {
        var term = termInput.value.trim();

        if (term.length < 2) {
            hideResults();
            return;
        }

        var token = ++currentRequestToken;
        var url = baseUrl + '&fnc=search&tagtype=' + encodeURIComponent(typeSelect.value) + '&q=' + encodeURIComponent(term);

        fetch(url, {credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (items) {
                if (token !== currentRequestToken) {
                    return; // a newer search started meanwhile, ignore this stale response
                }
                renderResults(items);
            })
            .catch(function () {
                if (token === currentRequestToken) {
                    hideResults();
                }
            });
    }

    termInput.addEventListener('input', function () {
        clearSelection();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(runSearch, 300);
    });

    typeSelect.addEventListener('change', function () {
        clearTimeout(searchTimeout);
        termInput.placeholder = typeSelect.options[typeSelect.selectedIndex].dataset.placeholder;
        clearSelection();
        hideResults();
        if (termInput.value.trim().length >= 2) {
            runSearch();
        }
    });
})();
</script>

[{include file="bottomitem.tpl"}]
