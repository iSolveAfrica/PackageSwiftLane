<?php
/** Small markup helpers for design-system forms (sl-field / sl-in / sl-sel / sl-seg). All output is escaped. */
require_once __DIR__ . '/sl_view.php';

/** Text-like input in a labelled sl-field. $attrs: extra raw attribute string built by the caller from trusted values. */
function sl_fInput(string $label, string $name, $value = '', string $type = 'text', string $attrs = '', string $note = ''): string
{
    return '<label class="sl-field"><span class="sl-lab">' . h($label) . '</span><input class="sl-in" type="' . h($type) . '" name="' . h($name) . '" value="' . h($value) . '" ' . $attrs . '>'
        . ($note !== '' ? '<span class="sl-note">' . h($note) . '</span>' : '') . '</label>';
}

/** Native select in a labelled sl-field. $options: value => label. */
function sl_fSelect(string $label, string $name, array $options, $selected = '', string $placeholder = ''): string
{
    $o = $placeholder !== '' ? '<option value="">' . h($placeholder) . '</option>' : '';
    foreach ($options as $v => $l) {
        $o .= '<option value="' . h($v) . '"' . ((string) $v === (string) $selected ? ' selected' : '') . '>' . h($l) . '</option>';
    }
    return '<label class="sl-field"><span class="sl-lab">' . h($label) . '</span><span class="sl-sel native"><select name="' . h($name) . '">' . $o . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>';
}

/** Radio group drawn as a boxed segmented control. $options: value => label. */
function sl_fRadios(string $label, string $name, array $options, $selected): string
{
    $out = '<div class="sl-field"><span class="sl-lab">' . h($label) . '</span><div class="sl-seg boxed" role="radiogroup" aria-label="' . h($label) . '">';
    foreach ($options as $v => $l) {
        $on = (string) $v === (string) $selected;
        $out .= '<label class="' . ($on ? 'on' : '') . '" style="padding:0 12px;height:30px;display:inline-flex;align-items:center;border-radius:9px;cursor:pointer" data-sl-radio>'
            . '<input type="radio" name="' . h($name) . '" value="' . h($v) . '"' . ($on ? ' checked' : '') . ' style="margin-right:6px">' . h($l) . '</label>';
    }
    return $out . '</div></div>';
}

/** Script that keeps the "on" class of a [data-sl-radio] segmented control in step with its checked radio. */
function sl_fRadioScript(): string
{
    return "<script>document.addEventListener('change',function(e){var l=e.target.closest('[data-sl-radio]');if(!l)return;l.parentNode.querySelectorAll('[data-sl-radio]').forEach(function(x){x.classList.toggle('on',x.querySelector('input').checked);});});</script>";
}

/**
 * Photo / file upload card: a data-sl-form posting a single file input to a legacy endpoint.
 * $hidden: name => value for the hidden inputs the endpoint expects.
 */
function sl_fPhotoForm(string $title, string $endpoint, string $field, string $src, array $hidden, bool $enabled = true, string $note = 'JPEG, PNG or GIF', bool $round = true): string
{
    $id = 'ph-' . preg_replace('/[^a-z0-9]/i', '', $field) . substr(md5($endpoint), 0, 4);
    $out = '<form class="sl-card col g12" data-sl-form data-sl-reload action="' . h($endpoint) . '" method="post" enctype="multipart/form-data">'
        . '<div class="sl-ch" style="margin:0"><h2 class="sl-ct">' . h($title) . '</h2></div>'
        . '<div class="row g12"><img id="' . h($id) . '" src="' . h($src) . '" alt="' . h($title) . '" style="width:72px;height:72px;object-fit:cover;border-radius:' . ($round ? '50%' : '10px') . ';border:1px solid var(--line);background:var(--field)">'
        . '<div class="col g6 grow">';
    foreach ($hidden as $k => $v) {
        $out .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    if ($enabled) {
        $out .= '<input class="sl-in" type="file" name="' . h($field) . '" accept="image/*" aria-label="Choose ' . h(strtolower($title)) . '" onchange="var f=this.files[0];if(f){document.getElementById(\'' . h($id) . '\').src=URL.createObjectURL(f);}">'
            . '<span class="sl-note">' . h($note) . '</span>';
    } else {
        $out .= '<span class="mut sm">You cannot change this.</span>';
    }
    $out .= '</div></div>';
    if ($enabled) {
        $out .= '<div><button type="submit" class="sl-btn sm"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</button> <a class="sl-btn ghost sm" href="' . h($src) . '" target="_blank" rel="noopener"><i class="ic ic-sm i-eye" aria-hidden="true"></i>View</a></div>';
    }
    return $out . '</form>';
}
