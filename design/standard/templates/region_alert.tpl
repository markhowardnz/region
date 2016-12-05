<div id="dialog-confirm" style="display: none;">
    <p>{"Select your region"|i18n('region')}</p>
</div>
{def
    $current_SA = siteaccess('name')
    $current_region = ezini( 'RegionalSettings', 'TranslationSA' )[$current_SA]
    $detected_region = detected_region()
    $detected_locale= detected_locale()
}
{debug-log var=$detected_locale msg='detected locale'}
{debug-log var=$detected_region msg='detected region'}
{def $temp="Go to our %region site"|d18n('region','',hash('%region', $detected_region ),$detected_locale )}
<script type="text/javascript">
    var geo_go = "{$temp}";
    var geo_continue = "{"Continue to our %region site"|d18n('region','',hash('%region', $current_region ), $detected_locale )}";
</script>

