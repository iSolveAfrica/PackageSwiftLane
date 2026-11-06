#!/usr/bin/env python3
"""
One-time scaffold converter: approved design artboards (.dc.html) -> PHP view templates.

  python3 scripts/sl_convert.py docs/handover/artifact-source views/sl/pages assets/swiftlane/pages [Board ...]

For every artboard it writes
  * assets/swiftlane/pages/<Board>.css  - the artboard's own <helmet><style> rules (verbatim)
  * views/sl/pages/<Board>.php          - the page body with design holes turned into PHP:
        {{a.b}}                       -> <?= sl_v($d, 'a.b') ?>           (escaped)
        <sc-for list="{{x}}" as="r">  -> <?php foreach ((array) sl_get($d, 'x', []) as $r): ?>
        <sc-if value="{{c}}">         -> <?php if (sl_get($d, 'c')): ?>
        href="Board.dc.html"          -> href="<?= sl_route('Board') ?>"
        onClick="{{fn}}"              -> data-sl-on="fn"
The generated template is a starting point: each page controller then supplies real
data in $d (see the contract comment at the top of the generated file).
"""
import os, re, sys, json

src, out_php, out_css = sys.argv[1], sys.argv[2], sys.argv[3]
only = set(sys.argv[4:])
os.makedirs(out_php, exist_ok=True); os.makedirs(out_css, exist_ok=True)

HOLE = re.compile(r'\{\{\s*([A-Za-z_$][\w.$]*)\s*\}\}')

RESERVED = {'d', 'this', 'lang', 'core', 'user', 'db', 'settings', 'permissions', 'userData', 'slPage'}

def alias_var(alias):
    return '$' + ('it_' + alias if alias in RESERVED else alias)

def php_path(expr, scopes):
    """Return (phpvar, path) for a hole like r.inv given loop scopes [(alias, listexpr)]"""
    expr = expr.strip()
    if expr == '$index':
        return ('$__i', '')
    head, _, rest = expr.partition('.')
    aliases = {a for a, _ in scopes}
    if head in aliases:
        return (alias_var(head), rest)
    return ('$d', expr)

def conv_hole(m, scopes):
    var, path = php_path(m.group(1), scopes)
    if var == '$__i':
        return '<?= (int) $__i ?>'
    if path == '':
        return "<?= sl_v(%s, '') ?>" % var if var != '$d' else ''
    return "<?= sl_v(%s, '%s') ?>" % (var, path)

def convert(body):
    scopes = []
    out = []
    pos = 0
    tag = re.compile(r'<sc-for\s+list="\{\{\s*([\w.$]+)\s*\}\}"\s+as="(\w+)"[^>]*>|</sc-for>|<sc-if\s+value="\{\{\s*([\w.$!]+)\s*\}\}"[^>]*>|</sc-if>', re.S)
    for m in tag.finditer(body):
        chunk = body[pos:m.start()]
        out.append(HOLE.sub(lambda h: conv_hole(h, scopes), chunk))
        t = m.group(0)
        if t.startswith('<sc-for'):
            lst, alias = m.group(1), m.group(2)
            var, path = php_path(lst, scopes)
            src_expr = "sl_get(%s, '%s', [])" % (var, path) if path else var
            out.append("<?php foreach ((array) %s as $__i => %s): ?>" % (src_expr, alias_var(alias)))
            scopes.append((alias, lst))
        elif t == '</sc-for>':
            if scopes:
                out.append('<?php endforeach; ?>')
                scopes.pop()
            else:
                out.append('<!-- stray </sc-for> in the design source: review this block -->')
        elif t.startswith('<sc-if'):
            cond = m.group(3)
            neg = cond.startswith('!')
            var, path = php_path(cond.lstrip('!'), scopes)
            expr = "sl_get(%s, '%s')" % (var, path) if path else var
            out.append("<?php if (%s%s): ?>" % ('!' if neg else '', expr))
        else:
            out.append('<?php endif; ?>')
        pos = m.end()
    out.append(HOLE.sub(lambda h: conv_hole(h, scopes), body[pos:]))
    s = ''.join(out)
    # links between artboards
    s = re.sub(r'href="([A-Za-z0-9-]+)\.dc\.html(#[\w-]*)?"', lambda m: 'href="<?= sl_route(\'%s\') ?>%s"' % (m.group(1), m.group(2) or ''), s)
    s = re.sub(r'href="/([A-Za-z0-9-]+)\.dc\.html"', lambda m: 'href="<?= sl_route(\'%s\') ?>"' % m.group(1), s)
    # event handlers -> data attributes for page scripts
    s = re.sub(r'\s(on[A-Z]\w*)="\{\{\s*([\w.$]+)\s*\}\}"', lambda m: ' data-sl-on="%s:%s"' % (m.group(1)[2:].lower(), m.group(2)), s)
    s = re.sub(r'\s(on[A-Z]\w*)="<\?= sl_v\(\$\w+, \'([\w.]+)\'\) \?>"', lambda m: ' data-sl-on="%s:%s"' % (m.group(1)[2:].lower(), m.group(2)), s)
    s = re.sub(r'\shint-placeholder-\w+="[^"]*"', '', s)
    return s

def holes_of(body):
    vals = sorted(set(HOLE.findall(body)))
    loops = re.findall(r'<sc-for\s+list="\{\{\s*([\w.$]+)\s*\}\}"\s+as="(\w+)"', body)
    conds = re.findall(r'<sc-if\s+value="\{\{\s*([\w.$!]+)\s*\}\}"', body)
    return vals, loops, conds

for fn in sorted(os.listdir(src)):
    if not fn.endswith('.dc.html'):
        continue
    name = fn[:-8]
    if only and name not in only:
        continue
    html = open(os.path.join(src, fn), encoding='utf-8').read()
    helm = re.search(r'<helmet>(.*?)</helmet>', html, re.S)
    css = ''
    if helm:
        for st in re.findall(r'<style>(.*?)</style>', helm.group(1), re.S):
            css += st.strip() + '\n'
    css = re.sub(r'^\s*body\{margin:0\}\s*$', '', css, flags=re.M).strip()
    if css:
        open(os.path.join(out_css, name + '.css'), 'w', encoding='utf-8').write('/* Page styles of %s (from the approved design) */\n%s\n' % (fn, css))
    body = html.split('</helmet>', 1)[1] if '</helmet>' in html else html
    body = body.split('</x-dc>', 1)[0]
    shell = 'class="sl-side"' in body
    wrap_attrs = ''
    if shell:
        m = re.search(r'<main class="sl-main"[^>]*>(.*)</main>', body, re.S)
        inner = m.group(1) if m else body
        w = re.match(r'\s*<div class="sl-wrap([^"]*)"([^>]*)>(.*)</div>\s*$', inner, re.S)
        if w:
            wrap_attrs = (w.group(1) + ' ' + w.group(2)).strip()
            inner = w.group(3)
    else:
        m = re.search(r'<x-dc>(.*)', body, re.S)
        inner = m.group(1) if m else body
    vals, loops, conds = holes_of(inner)
    php = convert(inner).strip('\n')
    script = re.search(r'<script type="text/x-dc"[^>]*>(.*?)</script>', html, re.S)
    head = ['<?php', '/**', ' * %s - generated from docs/handover/artifact-source/%s by scripts/sl_convert.py.' % (name, fn),
            ' * Shell page: %s' % ('yes (rendered inside views/sl/inc/shell_start.php)' if shell else 'no (plain page)'),
            ' * Data contract ($d):']
    for l in loops:
        head.append(' *   loop  %-28s as $%s' % (l[0], l[1]))
    for c in conds:
        head.append(' *   if    %s' % c)
    simple = [v for v in vals if '.' not in v and v != '$index']
    if simple:
        head.append(' *   value ' + ', '.join(simple))
    if wrap_attrs:
        head.append(' * sl-wrap extras in the design: %s' % wrap_attrs)
    head.append(' * Demo data shapes: see the renderVals() script in the artifact source file.')
    head.append(' */')
    head.append('if (!isset($d)) { $d = []; }')
    head.append('?>')
    open(os.path.join(out_php, name + '.php'), 'w', encoding='utf-8').write('\n'.join(head) + '\n' + php + '\n')
    print('%-30s shell=%s css=%5d holes=%3d loops=%2d' % (name, int(shell), len(css), len(vals), len(loops)))
