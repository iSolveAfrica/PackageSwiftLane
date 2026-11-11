<?php
/** Pure price and geographic rules. Amounts are integer minor currency units. */
function dl_price(string $mode, array $rates, int $metres, int $parcels, ?int $zoneCents): array
{
    if ($metres < 0 || $parcels < 1 || $parcels > 50) throw new DomainException('Invalid distance or parcel count');
    $base = (int) ($rates['base_cents'] ?? 0); $minimum = (int) ($rates['minimum_cents'] ?? 0);
    if ($base < 0 || $minimum < 0) throw new DomainException('Invalid delivery rates');
    switch ($mode) {
        case 'kilometre': $unit = (int) ($rates['per_km_cents'] ?? 0); if ($unit <= 0) throw new DomainException('Kilometre pricing is not configured'); $variable = (int) ceil($metres * $unit / 1000); break;
        case 'package': $unit = (int) ($rates['per_package_cents'] ?? 0); if ($unit <= 0) throw new DomainException('Package pricing is not configured'); $variable = $unit * $parcels; break;
        case 'zone': if ($zoneCents === null || $zoneCents < 0) throw new DomainException('No delivery zone covers this location'); $unit = $variable = $zoneCents; break;
        default: throw new DomainException('Choose a configured pricing model');
    }
    $total = max($minimum, $base + $variable);
    if ($total < 0 || $total > 100000000) throw new DomainException('Delivery amount is outside supported limits');
    return ['mode'=>$mode,'base_cents'=>$base,'unit_cents'=>$unit,'variable_cents'=>$variable,'minimum_cents'=>$minimum,'parcels'=>$parcels,'distance_m'=>$metres,'total_cents'=>$total];
}
function dl_km(float $a, float $b, float $c, float $d): float
{
    $x=sin(deg2rad($c-$a)/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin(deg2rad($d-$b)/2)**2;
    return 6371*2*asin(min(1,sqrt(max(0,$x))));
}
function dl_inside(float $lat,float $lng,array $polygon): bool
{
    $inside=false; $n=count($polygon);
    for($i=0,$j=$n-1;$i<$n;$j=$i++){
        [$a,$b]=$polygon[$i];[$c,$d]=$polygon[$j];
        if (($b>$lng)!==($d>$lng) && $lat<($c-$a)*($lng-$b)/($d-$b)+$a) $inside=!$inside;
    }
    return $inside;
}
function dl_zone(array $zones, array $place, int $agency): ?object
{
    // Polygon > radius > exact Google locality. Priority resolves within each kind;
    // equal-ranked overlapping zones are rejected instead of charging arbitrarily.
    $matches=[];
    foreach($zones as $z){
        if((int)$z->agency_id && (int)$z->agency_id!==$agency)continue;
        $poly=json_decode((string)$z->polygon_json,true);$kind=0;$match=false;
        if($poly){$kind=0;$match=dl_inside($place['lat'],$place['lng'],$poly);}
        elseif((float)$z->radius_km>0){$kind=1;$match=dl_km($place['lat'],$place['lng'],(float)$z->centre_lat,(float)$z->centre_lng)<=(float)$z->radius_km;}
        else{$kind=2;foreach(explode(',',(string)$z->city_match) as $city)foreach($place['localities'] as $locality)if(mb_strtolower(trim($city))===mb_strtolower(trim($locality))&&trim($city)!=='')$match=true;}
        if($match)$matches[]=['zone'=>$z,'rank'=>$kind*100000+(int)$z->priority];
    }
    usort($matches,fn($a,$b)=>$a['rank']<=>$b['rank']);
    if(count($matches)>1&&$matches[0]['rank']===$matches[1]['rank'])throw new DomainException('Overlapping delivery zones need different priorities. Please contact dispatch.');
    return $matches[0]['zone']??null;
}
