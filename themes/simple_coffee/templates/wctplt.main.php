<?php

# ============================================================================
#                  CPCHAT MAIN
# ============================================================================

$templates['cpchat'] = '
<noscript>-- Javascript must be enabled! --</noscript>
<div class="closed" id="wc_loader_img_c">
    <div class="center">
        <img src="{INCLUDE_DIR_THEME}images/loader.gif" id="wc_loader_img">
    </div>
</div>
<div id="wcchat">
        <div class="right_col">
            {ROOM_LIST}
            {USER_LIST}
            {THEMES}
            <div class="copyright_note">
                Powered by: <a href="https://github.com/rexzooly/CoffeePot-Chat" target="_blank">CoffeePot Chat 1.5</a>
            </div>
        </div>
        <div class="left_col">
            {TOPIC}
            {STATIC_MSG}
            {POSTS}{GSETTINGS}{INFO}
            {TOOLBAR}
            {TEXT_INPUT}
            {SETTINGS}
            {JOIN}            
        </div>
</div>';

$templates['cpchat.critical_error'] = '
<noscript>-- Javascript must be enabled! --</noscript>
<div id="wcchat">
    <fieldset class="critical_error">
        <legend>Critical Error</legend>
        {ERROR}
    </fieldset>
</div>';

$templates['cpchat.botNoAccessNote'] = '403: Forbidden; Powered by: <a href="https://github.com/rexzooly/CoffeePot-Chat" target="_blank">{SCRIPT_NAME} {SCRIPT_VERSION}</a>';

//Below is the older template will be removed when wc codes are dropped.
$templates['wcchat'] = '
<noscript>-- Javascript must be enabled! --</noscript>
<div class="closed" id="wc_loader_img_c">
    <div class="center">
        <img src="{INCLUDE_DIR_THEME}images/loader.gif" id="wc_loader_img">
    </div>
</div>
<div id="wcchat">
        <div class="right_col">
            {ROOM_LIST}
            {USER_LIST}
            {THEMES}
            <div class="copyright_note">
                Powered by: <a href="https://github.com/rexzooly/CoffeePot-Chat" target="_blank">{SCRIPT_NAME} {SCRIPT_VERSION}</a>
            </div>
        </div>
        <div class="left_col">
            {TOPIC}
            {STATIC_MSG}
            {POSTS}{GSETTINGS}{INFO}
            {TOOLBAR}
            {TEXT_INPUT}
            {SETTINGS}
            {JOIN}            
        </div>
</div>';

$templates['wcchat.critical_error'] = '
<noscript>-- Javascript must be enabled! --</noscript>
<div id="wcchat">
    <fieldset class="critical_error">
        <legend>Critical Error</legend>
        {ERROR}
    </fieldset>
</div>';

$templates['wcchat.botNoAccessNote'] = '403: Forbidden; Powered by: <a href="https://github.com/rexzooly/CoffeePot-Chat" target="_blank">{SCRIPT_NAME} {SCRIPT_VERSION}</a>';
?>