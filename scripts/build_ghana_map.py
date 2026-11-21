import json, math, re, sys
land = json.load(open(sys.argv[1])); places = json.load(open(sys.argv[2])); out_dir = sys.argv[3]
gh = None
for f in land['features']:
    p = f['properties']
    if 'GHA' in json.dumps(p) or 'Ghana' in json.dumps(p): gh = f; break
geom = gh['geometry']
polys = geom['coordinates'] if geom['type'] == 'MultiPolygon' else [geom['coordinates']]
# projection: equirectangular, x scaled by cos(8 deg)
LON0, LON1, LAT0, LAT1 = -3.35, 1.3, 4.6, 11.25
K = math.cos(math.radians(7.9)); W = 400.0
sx = W / ((LON1 - LON0) * K); H = round((LAT1 - LAT0) * sx, 1)
def xy(lon, lat): return round((lon - LON0) * K * sx, 1), round((LAT1 - lat) * sx, 1)
d = ''
for poly in polys:
    ring = poly[0]
    if len(ring) < 8: continue
    pts = [xy(*c) for c in ring]
    # light simplification: drop points closer than 1.2px
    keep = [pts[0]]
    for q in pts[1:]:
        if abs(q[0]-keep[-1][0]) + abs(q[1]-keep[-1][1]) >= 0.7: keep.append(q)
    d += 'M' + ' L'.join(f'{x},{y}' for x, y in keep) + 'Z'
regions = {
 'greater-accra': ('Greater Accra', 'Accra', 5.56, -0.20), 'ashanti': ('Ashanti', 'Kumasi', 6.69, -1.62), 'western': ('Western', 'Sekondi-Takoradi', 4.93, -1.76),
 'western-north': ('Western North', 'Sefwi Wiawso', 6.21, -2.49), 'central': ('Central', 'Cape Coast', 5.11, -1.25), 'eastern': ('Eastern', 'Koforidua', 6.09, -0.26),
 'volta': ('Volta', 'Ho', 6.60, 0.47), 'oti': ('Oti', 'Dambai', 8.07, 0.18), 'northern': ('Northern', 'Tamale', 9.40, -0.84), 'savannah': ('Savannah', 'Damongo', 9.08, -1.82),
 'north-east': ('North East', 'Nalerigu', 10.53, -0.37), 'upper-east': ('Upper East', 'Bolgatanga', 10.79, -0.85), 'upper-west': ('Upper West', 'Wa', 10.06, -2.50),
 'bono': ('Bono', 'Sunyani', 7.34, -2.33), 'bono-east': ('Bono East', 'Techiman', 7.59, -1.94), 'ahafo': ('Ahafo', 'Goaso', 6.80, -2.52)}
byname = {v[0].lower(): k for k, v in regions.items()}
byname['brong-ahafo'] = 'bono'
R = {}
for r in places['regions']:
    n = r['name'].replace(' Region', '').lower()
    if n in byname: R[r['id']] = byname[n]
best = {}
for c in places['cities']:
    rk = R.get(c.get('regionId'))
    if not rk or c.get('latitude') is None: continue
    name = re.sub(r'\s+', ' ', c['name'].strip().lower())
    pop = c.get('population') or 0
    cap = regions[rk]
    # coordinates must be plausible for the region (data has duplicate names merged)
    near = math.hypot(c['latitude'] - cap[2], c['longitude'] - cap[3]) < 2.2
    score = (near, pop)
    if name not in best or score > best[name][0]:
        best[name] = (score, [rk, round(c['latitude'], 3), round(c['longitude'], 3)] if near else [rk, None, None])
pl = {k: v[1] for k, v in best.items()}
# common Accra / Tema / Kumasi neighbourhoods missing from the dataset
extra = {'greater-accra': ['east legon','west legon','north legon','spintex','airport residential','east airport','osu','labone','cantonments','dzorwulu','haatso','kwabenya','ashongman','pokuase','amasaman','weija','mccarthy hill','dansoman','kaneshie','lapaz','abeka','tesano','nungua','sakumono','baatsonaa','oyarifa','ashale botwe','trassaco','ridge','kokomlemle','adabraka','darkuman','odorkor','mallam','gbawe','taifa','dome','madina','adenta','achimota','teshie','la','labadi','tema','community','sakaman','ofankor','abokobi','kpone','dawhenya','prampram','batsonaa','kotobabi','north kaneshie','alajo','roman ridge','east cantonments','legon','west hills','santa maria','awoshie','sowutuom','ablekuma','kissieman','agbogba','manet','regimanuel','tse addo','kasoa new market','medina','medina estates'],
 'ashanti': ['kumasi','ahodwo','asokwa','bantama','suame','tafo','kwadaso','ayeduase','kotei','ejisu','ayigya','santasi','adum','atonsu','buokrom','abuakwa'],
 'central': ['kasoa','winneba','cape coast','swedru','mankessim','elmina'], 'western': ['takoradi','sekondi','tarkwa'], 'eastern': ['koforidua','nkawkaw','aburi','nsawam']}
for rk, names in extra.items():
    cap = regions[rk]
    for n in names:
        cur = pl.get(n)
        if not cur or cur[0] != rk: pl[n] = [rk, None, None]
json.dump({'w': W, 'h': H, 'proj': [LON0, LAT1, K, sx], 'outline': d,
  'regions': {k: {'name': v[0], 'capital': v[1], 'xy': xy(v[3], v[2])} for k, v in regions.items()}},
  open(out_dir + '/ghana-map.json', 'w'), separators=(',', ':'))
json.dump(pl, open(out_dir + '/ghana-places.json', 'w'), separators=(',', ':'))
print('outline chars', len(d), 'places', len(pl), 'h', H)
