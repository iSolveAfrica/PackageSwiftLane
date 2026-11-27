<?php
/**
 * Which phone, tablet or computer, and which browser, is asking.
 *
 * Layout never depends on this: width and pointer media queries decide phone / tablet / desktop layout, so a
 * wrong guess cannot break a page. Detection only personalises: the install steps for this exact browser, the
 * name of the biometric ("Face ID", "Fingerprint", "Windows Hello"), which maps app a rider is offered, the
 * label of "this device" in the security screens, and small platform manners (helpers below, and
 * assets/swiftlane/pwa/device.js for what only the browser itself can know).
 *
 * Sources, most trusted first: the sl_dev cookie written by device.js after it has looked at the real browser
 * (an iPad says it is a Mac, an installed app has no address bar), then User-Agent Client Hints, then the
 * User-Agent string. All of it is a hint from the client: never use it for a security decision.
 */

if (!function_exists('sl_device')) {

    /** Parse a User-Agent string (and optional client hints) into the device description. Pure, testable. */
    function sl_deviceParse(string $ua, array $hints = []): array
    {
        $ua = substr($ua, 0, 512);
        $has = static fn(string $re): bool => (bool) preg_match($re, $ua);
        $platformHint = strtolower(trim((string) ($hints['platform'] ?? ''), "\" \t"));
        $mobileHint = (string) ($hints['mobile'] ?? '');
        $modelHint = trim((string) ($hints['model'] ?? ''), "\" \t");

        // operating system
        if ($has('/iPhone|iPod/')) {
            $os = 'ios';
        } elseif ($has('/iPad/')) {
            $os = 'ios';
        } elseif ($has('/Android/') || $platformHint === 'android') {
            $os = 'android';
        } elseif ($has('/Windows NT|Win64|WOW64/') || $platformHint === 'windows') {
            $os = 'windows';
        } elseif ($has('/Macintosh|Mac OS X/') || $platformHint === 'macos') {
            $os = 'macos';
        } elseif ($has('/CrOS|Linux|X11/') || in_array($platformHint, ['linux', 'chrome os', 'chromeos'], true)) {
            $os = 'linux';
        } else {
            $os = 'other';
        }

        // browser. Order matters: every Chromium browser also says "Chrome" and "Safari".
        $inApp = '';
        foreach (['Facebook' => '/FBAN|FBAV|FB_IAB/', 'Instagram' => '/Instagram/', 'WhatsApp' => '/WhatsApp/', 'TikTok' => '/TikTok|musical_ly|BytedanceWebview/', 'Snapchat' => '/Snapchat/', 'LINE' => '/\bLine\//', 'X' => '/Twitter/', 'LinkedIn' => '/LinkedInApp/', 'Telegram' => '/Telegram/', 'Google app' => '/\bGSA\//'] as $app => $re) {
            if ($has($re)) {
                $inApp = $app;
                break;
            }
        }
        if ($inApp === '' && $os === 'android' && $has('/; wv\)/')) {
            $inApp = 'another app';
        }
        if ($has('/SamsungBrowser/')) {
            [$browser, $browserLabel] = ['samsung', 'Samsung Internet'];
        } elseif ($has('/EdgA|EdgiOS|Edg\//')) {
            [$browser, $browserLabel] = ['edge', 'Edge'];
        } elseif ($has('/OPR\/|OPiOS|OPT\/|Opera/')) {
            [$browser, $browserLabel] = ['opera', $has('/Opera Mini|OPiOS/') ? 'Opera Mini' : 'Opera'];
        } elseif ($has('/FxiOS|Firefox/')) {
            [$browser, $browserLabel] = ['firefox', 'Firefox'];
        } elseif ($has('/HuaweiBrowser/')) {
            [$browser, $browserLabel] = ['huawei', 'Huawei Browser'];
        } elseif ($has('/MiuiBrowser/')) {
            [$browser, $browserLabel] = ['miui', 'Mi Browser'];
        } elseif ($has('/UCBrowser|UCWEB/')) {
            [$browser, $browserLabel] = ['uc', 'UC Browser'];
        } elseif ($has('/PHX\//')) {
            [$browser, $browserLabel] = ['phoenix', 'Phoenix Browser'];
        } elseif ($has('/CriOS|Chrome|Chromium/')) {
            [$browser, $browserLabel] = ['chrome', 'Chrome'];
        } elseif ($has('/Safari/') && $has('/Version\/|iPhone|iPad|Macintosh/')) {
            [$browser, $browserLabel] = ['safari', 'Safari'];
        } else {
            [$browser, $browserLabel] = ['other', 'your browser'];
        }
        if ($inApp !== '') {
            [$browser, $browserLabel] = ['inapp', $inApp === 'another app' ? 'an in-app browser' : $inApp . ' browser'];
        }

        // phone, tablet or computer
        if ($has('/iPad/') || ($os === 'android' && !$has('/Mobile/') && $mobileHint !== '?1') || $has('/Tablet|Kindle|Silk|PlayBook/')) {
            $form = 'tablet';
        } elseif ($has('/iPhone|iPod|Mobile|Opera Mini|IEMobile/') || $mobileHint === '?1') {
            $form = 'phone';
        } else {
            $form = 'desktop';
        }

        // what people call the device. Chrome on Android now reports the model as "K" unless hints are sent.
        $model = '';
        if ($has('/iPhone/')) {
            $model = 'iPhone';
        } elseif ($has('/iPad/')) {
            $model = 'iPad';
        } elseif ($has('/iPod/')) {
            $model = 'iPod touch';
        } elseif ($os === 'android') {
            $raw = $modelHint;
            if ($raw === '' && preg_match('/Android [\d.]+; (?:[a-z]{2}-[a-z]{2}; )?([^;)]+?)(?: Build\/[^;)]*)?[;)]/i', $ua, $m)) {
                $raw = trim($m[1]);
            }
            if ($raw === 'K' || strcasecmp($raw, 'wv') === 0) {
                $raw = '';
            }
            $families = ['/^SM-|^GT-|^SAMSUNG/i' => 'Samsung Galaxy', '/^Pixel/i' => 'Google Pixel', '/^TECNO|^Tecno/i' => 'Tecno', '/^Infinix/i' => 'Infinix', '/^itel/i' => 'itel', '/Redmi|^Mi |^M2\d{3}|^2\d{3}[0-9A-Z]{4,}|POCO|Xiaomi/i' => 'Xiaomi', '/HUAWEI|^HW-|^[A-Z]{3}-[A-Z]{1,2}\d{2}/' => 'Huawei', '/OPPO|^CPH\d/i' => 'Oppo', '/vivo|^V\d{4}/i' => 'Vivo', '/Nokia/i' => 'Nokia', '/^moto|Motorola/i' => 'Motorola', '/OnePlus|^[A-Z]{2}\d{4}$/' => 'OnePlus', '/realme|^RMX/i' => 'Realme'];
            foreach ($families as $re => $name) {
                if ($raw !== '' && preg_match($re, $raw)) {
                    $model = $name;
                    break;
                }
            }
            if ($model === '') {
                $model = $form === 'tablet' ? 'Android tablet' : 'Android phone';
            }
        } elseif ($os === 'windows') {
            $model = 'Windows computer';
        } elseif ($os === 'macos') {
            $model = 'Mac';
        } elseif ($os === 'linux') {
            $model = $has('/CrOS/') ? 'Chromebook' : 'Linux computer';
        } else {
            $model = 'This device';
        }

        return sl_deviceFinish(['os' => $os, 'browser' => $browser, 'browserLabel' => $browserLabel, 'form' => $form, 'model' => $model, 'inApp' => $inApp, 'standalone' => false]);
    }

    /** Derives the labels from os / browser / form, so a correction from the browser re-labels everything. */
    function sl_deviceFinish(array $d): array
    {
        $osLabels = ['ios' => $d['form'] === 'tablet' ? 'iPadOS' : 'iOS', 'android' => 'Android', 'windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux', 'other' => ''];
        $d['osLabel'] = $osLabels[$d['os']] ?? '';
        $d['isPhone'] = $d['form'] === 'phone';
        $d['isTouch'] = $d['form'] !== 'desktop';
        // "Chrome on Android phone" is clumsy; people say "Samsung Galaxy · Chrome".
        $d['label'] = $d['form'] === 'desktop' ? $d['browserLabel'] . ' on ' . ($d['osLabel'] ?: 'this computer') : $d['model'] . ' · ' . $d['browserLabel'];
        $d['thing'] = $d['form'] === 'desktop' ? 'computer' : ($d['form'] === 'tablet' ? 'tablet' : 'phone');
        // The device unlock a passkey uses. An iPhone may have Face ID or Touch ID; we cannot tell which.
        $bio = ['ios' => 'Face ID or Touch ID', 'android' => 'Fingerprint or face unlock', 'windows' => 'Windows Hello', 'macos' => 'Touch ID'];
        $d['biometric'] = $bio[$d['os']] ?? 'Passkey';
        $d['biometricShort'] = ['ios' => 'Face ID', 'android' => 'Fingerprint', 'windows' => 'Windows Hello', 'macos' => 'Touch ID'][$d['os']] ?? 'Passkey';
        // Riders and customers get the maps app their phone opens natively offered first.
        $d['maps'] = $d['os'] === 'ios' || $d['os'] === 'macos' ? 'apple' : 'google';
        // Can this browser install the app, and how?
        $d['install'] = sl_deviceInstall($d);
        return $d;
    }

    /**
     * How the app gets onto the home screen from this browser.
     * way: 'prompt' (the browser shows its own install dialog), 'share' (Share › Add to Home Screen),
     *      'menu' (an item in the browser menu), 'leave' (in-app browser: open the real browser first), 'none'.
     */
    function sl_deviceInstall(array $d): array
    {
        $b = $d['browser'];
        $os = $d['os'];
        if ($b === 'inapp') {
            $open = $os === 'ios' ? 'Safari' : 'Chrome';
            return ['way' => 'leave', 'open' => $open, 'steps' => ['Tap the menu (··· or ⋮) at the top of this screen', 'Choose Open in ' . $open . ' or Open in browser', 'Install the app from there']];
        }
        if ($os === 'ios') {
            if ($b === 'safari') {
                return ['way' => 'share', 'steps' => ['Tap Share in the bar at the bottom', 'Scroll down and choose Add to Home Screen', 'Tap Add. The app appears on your home screen']];
            }
            // Since iOS 16.4 Chrome, Edge and Firefox can add to the home screen too, from their own Share menu.
            return ['way' => 'share', 'steps' => ['Tap Share, at the top right next to the address', 'Choose Add to Home Screen', 'Tap Add. If you do not see it, open this page in Safari'], 'fallback' => 'Safari'];
        }
        if ($os === 'android') {
            switch ($b) {
                case 'samsung':
                    return ['way' => 'prompt', 'steps' => ['Tap the menu ☰ at the bottom right', 'Choose Add to, then Home screen', 'Tap Add']];
                case 'firefox':
                    return ['way' => 'menu', 'steps' => ['Tap the menu ⋮ beside the address', 'Choose Install or Add app to Home screen', 'Tap Add']];
                case 'opera':
                    return ['way' => 'menu', 'steps' => ['Tap the menu ⋮', 'Choose Add to…, then Home screen', 'Tap Add']];
                case 'edge':
                    return ['way' => 'prompt', 'steps' => ['Tap the menu ☰ at the bottom', 'Choose Add to phone', 'Tap Install']];
                case 'chrome':
                    return ['way' => 'prompt', 'steps' => ['Tap the menu ⋮ at the top right', 'Choose Install app or Add to Home screen', 'Tap Install']];
                default:
                    return ['way' => 'menu', 'open' => 'Chrome', 'steps' => ['Open the browser menu', 'Choose Add to Home screen', 'If there is no such item, open this page in Chrome']];
            }
        }
        if (in_array($b, ['chrome', 'edge'], true)) {
            return ['way' => 'prompt', 'steps' => ['Click the install icon at the right of the address bar', 'Click Install']];
        }
        if ($b === 'safari' && $os === 'macos') {
            return ['way' => 'menu', 'steps' => ['Open the File menu', 'Choose Add to Dock']];
        }
        return ['way' => 'none', 'steps' => []];
    }

    /** The device of the current request. */
    function sl_device(): array
    {
        static $device = null;
        if ($device !== null) {
            return $device;
        }
        $device = sl_deviceParse((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), [
            'platform' => $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '',
            'mobile' => $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '',
            'model' => $_SERVER['HTTP_SEC_CH_UA_MODEL'] ?? '',
        ]);
        // What the browser itself reported on an earlier page (device.js): "os.form.standalone", e.g. "ios.tablet.1".
        $cookie = (string) ($_COOKIE['sl_dev'] ?? '');
        if (preg_match('/^(ios|android|windows|macos|linux|other)\.(phone|tablet|desktop)\.([01])$/', $cookie, $m)) {
            $changed = false;
            // Only the one correction a User-Agent cannot make: an iPad that reports itself as a Mac.
            if ($device['os'] === 'macos' && $m[1] === 'ios') {
                $device['os'] = 'ios';
                $device['form'] = $m[2] === 'phone' ? 'phone' : 'tablet';
                $device['model'] = 'iPad';
                $changed = true;
            }
            if ($m[3] === '1') {
                $device['standalone'] = true;
                $changed = true;
            }
            if ($changed) {
                $device = sl_deviceFinish($device);
            }
        }
        return $device;
    }

    /** Attributes for <html>: the CSS and scripts personalise from these. */
    function sl_deviceHtmlAttrs(): string
    {
        $d = sl_device();
        return ' data-os="' . htmlspecialchars($d['os'], ENT_QUOTES) . '" data-browser="' . htmlspecialchars($d['browser'], ENT_QUOTES) . '" data-form="' . htmlspecialchars($d['form'], ENT_QUOTES) . '"' . ($d['standalone'] ? ' data-standalone="1"' : '');
    }

    /** Ask Chromium browsers for the real phone model on later requests (the User-Agent only says "K"). */
    function sl_deviceRequestHints(): void
    {
        if (!headers_sent() && PHP_SAPI !== 'cli') {
            header('Accept-CH: Sec-CH-UA-Model, Sec-CH-UA-Platform, Sec-CH-UA-Mobile', false);
        }
    }

    /**
     * Directions to an address or a "lat,lng" pair in the maps app this device opens natively.
     * $prefer: 'google' | 'apple' | 'waze' | '' (the device default).
     */
    function sl_deviceMapsUrl(string $destination, string $prefer = ''): string
    {
        $app = $prefer !== '' ? $prefer : sl_device()['maps'];
        $isPair = (bool) preg_match('/^-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?$/', $destination);
        if ($app === 'waze') {
            return 'https://waze.com/ul?' . ($isPair ? 'll=' . rawurlencode(str_replace(' ', '', $destination)) : 'q=' . rawurlencode($destination)) . '&navigate=yes';
        }
        if ($app === 'apple') {
            return 'https://maps.apple.com/?daddr=' . rawurlencode($destination) . '&dirflg=d';
        }
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination) . '&travelmode=driving';
    }

    /** One line for "this device" rows: "iPhone · Safari · installed app". */
    function sl_deviceLine(): string
    {
        $d = sl_device();
        return $d['label'] . ($d['standalone'] ? ' · installed app' : '');
    }
}
