<?php
declare(strict_types=1);

namespace EmojiBot\Render;

/**
 * Trendy animated templates made from 3D emoji artwork
 * (assets/art — Microsoft Fluent Emoji, MIT license).
 */
final class ArtCatalog
{
    public const CAT_TREND = 'trend';
    public const CAT_SIGN = 'sign';
    public const CAT_RIBBON = 'ribbon';

    public const CATEGORY_TITLES = [
        self::CAT_TREND => '🔥 ترند متحرک',
        self::CAT_SIGN => '🐾 تابلو به دست',
        self::CAT_RIBBON => '🎀 روبان‌دار',
    ];

    /** file => [title, emoji, effect, box on the object [cx, cy, w, h, angle?] in artwork coords] */
    private const LABEL = [
        'trophy' => ['جام', '🏆', 'shine', [0.5, 0.30, 0.52, 0.24]],
        'coin' => ['سکه طلا', '🪙', 'flip', [0.5, 0.5, 0.6, 0.34]],
        'money_bag' => ['کیسه پول', '💰', 'wiggle', [0.5, 0.64, 0.62, 0.3]],
        'gem_stone' => ['الماس', '💎', 'shine', [0.5, 0.5, 0.62, 0.28]],
        'crown' => ['تاج', '👑', 'shine', [0.5, 0.64, 0.7, 0.22]],
        'ring' => ['انگشتر', '💍', 'shine', [0.5, 0.7, 0.6, 0.2]],
        'rocket' => ['موشک', '🚀', 'float', [0.5, 0.5, 0.56, 0.2, -45]],
        'fire' => ['آتش', '🔥', 'pulse', [0.5, 0.64, 0.6, 0.3]],
        'wrapped_gift' => ['کادو', '🎁', 'shake', [0.5, 0.66, 0.62, 0.28]],
        'birthday_cake' => ['کیک تولد', '🎂', 'pulse', [0.5, 0.64, 0.7, 0.24]],
        '1st_place_medal' => ['مدال طلا', '🥇', 'shine', [0.5, 0.64, 0.48, 0.3]],
        'red_heart' => ['قلب', '❤️', 'beat', [0.5, 0.47, 0.66, 0.32]],
        'sparkling_heart' => ['قلب درخشان', '💖', 'beat', [0.54, 0.52, 0.6, 0.3]],
        'heart_with_ribbon' => ['قلب کادویی', '💝', 'beat', [0.5, 0.56, 0.56, 0.28]],
        'shield' => ['سپر', '🛡️', 'shine', [0.5, 0.48, 0.56, 0.3]],
        'slot_machine' => ['اسلات', '🎰', 'blink', [0.47, 0.42, 0.56, 0.18]],
        'dollar_banknote' => ['دلار', '💵', 'wiggle', [0.5, 0.5, 0.72, 0.26]],
        'credit_card' => ['کارت', '💳', 'shine', [0.5, 0.6, 0.76, 0.22]],
        'handbag' => ['کیف', '👜', 'wiggle', [0.5, 0.64, 0.64, 0.26]],
        'shopping_bags' => ['خرید', '🛍️', 'wiggle', [0.42, 0.66, 0.46, 0.24]],
        'bubble_tea' => ['بابل‌تی', '🧋', 'wiggle', [0.5, 0.66, 0.5, 0.24]],
        'hot_beverage' => ['قهوه', '☕', 'float', [0.45, 0.56, 0.52, 0.26]],
        'laptop' => ['لپ‌تاپ', '💻', 'blink', [0.5, 0.4, 0.66, 0.26]],
        'mobile_phone' => ['موبایل', '📱', 'shake', [0.5, 0.5, 0.5, 0.3]],
        'soccer_ball' => ['توپ', '⚽', 'float', [0.5, 0.5, 0.7, 0.3]],
        'basketball' => ['بسکتبال', '🏀', 'float', [0.5, 0.5, 0.7, 0.3]],
        'glowing_star' => ['ستاره', '🌟', 'pulse', [0.5, 0.56, 0.46, 0.24]],
        'peach' => ['هلو', '🍑', 'beat', [0.5, 0.56, 0.62, 0.3]],
        'cookie' => ['کوکی', '🍪', 'wiggle', [0.5, 0.5, 0.68, 0.3]],
        'doughnut' => ['دونات', '🍩', 'pulse', [0.5, 0.5, 0.7, 0.3]],
        'crystal_ball' => ['گوی جادویی', '🔮', 'shine', [0.5, 0.42, 0.56, 0.26]],
        'light_bulb' => ['لامپ', '💡', 'blink', [0.5, 0.36, 0.52, 0.24]],
        'bomb' => ['بمب', '💣', 'shake', [0.45, 0.58, 0.6, 0.3]],
        'nazar_amulet' => ['چشم‌نظر', '🧿', 'pulse', [0.5, 0.5, 0.62, 0.3]],
        'top_hat' => ['کلاه', '🎩', 'wiggle', [0.5, 0.64, 0.64, 0.2]],
        'billed_cap' => ['کپ', '🧢', 'wiggle', [0.52, 0.42, 0.5, 0.22]],
        'teddy_bear' => ['خرس عروسکی', '🧸', 'wiggle', [0.5, 0.68, 0.44, 0.22]],
        'balloon' => ['بادکنک', '🎈', 'float', [0.55, 0.34, 0.5, 0.26]],
        'alarm_clock' => ['ساعت', '⏰', 'shake', [0.5, 0.56, 0.52, 0.22]],
        'chart_increasing' => ['نمودار', '📈', 'shine', [0.5, 0.5, 0.66, 0.26]],
        'pile_of_poo' => ['پوپو', '💩', 'wiggle', [0.5, 0.72, 0.6, 0.22]],
        'four_leaf_clover' => ['شبدر', '🍀', 'wiggle', [0.5, 0.45, 0.6, 0.28]],
        'mushroom' => ['قارچ', '🍄', 'pulse', [0.5, 0.34, 0.66, 0.24]],
        'collision' => ['انفجار', '💥', 'pulse', [0.5, 0.5, 0.56, 0.28]],
    ];

    /** file => [title, emoji] — character peeking over a sign it holds (animated: peek) */
    private const SIGN = [
        'cat_face' => ['گربه', '🐱'],
        'dog_face' => ['سگ', '🐶'],
        'frog' => ['قورباغه', '🐸'],
        'panda' => ['پاندا', '🐼'],
        'bear' => ['خرس', '🐻'],
        'fox' => ['روباه', '🦊'],
        'rabbit_face' => ['خرگوش', '🐰'],
        'tiger_face' => ['ببر', '🐯'],
        'monkey_face' => ['میمون', '🐵'],
        'pig_face' => ['خوک', '🐷'],
        'koala' => ['کوالا', '🐨'],
        'hamster' => ['همستر', '🐹'],
        'lion' => ['شیر', '🦁'],
        'wolf' => ['گرگ', '🐺'],
        'alien' => ['آدم فضایی', '👽'],
        'robot' => ['ربات', '🤖'],
        'skull' => ['جمجمه', '💀'],
        'clown_face' => ['دلقک', '🤡'],
        'smiling_face_with_sunglasses' => ['باحال', '😎'],
        'money-mouth_face' => ['پولدار', '🤑'],
        'star-struck' => ['ذوق‌زده', '🤩'],
        'face_with_tears_of_joy' => ['خنده', '😂'],
        'cowboy_hat_face' => ['کابوی', '🤠'],
        'smiling_cat_with_heart-eyes' => ['گربه عاشق', '😻'],
        'see-no-evil_monkey' => ['خجالتی', '🙈'],
        'smiling_face_with_hearts' => ['عاشق', '🥰'],
        'ghost' => ['روح', '👻'],
        'baby_chick' => ['جوجه', '🐤'],
        'penguin' => ['پنگوئن', '🐧'],
        'ninja' => ['نینجا', '🥷'],
    ];

    /** file => [title, emoji] — object floating above a name banner (animated: hover) */
    private const BANNER = [
        'rose' => ['رز', '🌹'],
        'tulip' => ['لاله', '🌷'],
        'bouquet' => ['دسته گل', '💐'],
        'sunflower' => ['آفتابگردان', '🌻'],
        'cherry_blossom' => ['شکوفه', '🌸'],
        'magic_wand' => ['چوب جادو', '🪄'],
        'sparkles' => ['درخشش', '✨'],
        'high_voltage' => ['صاعقه', '⚡'],
        'party_popper' => ['جشن', '🎉'],
        'crossed_swords' => ['شمشیرها', '⚔️'],
        'guitar' => ['گیتار', '🎸'],
        'microphone' => ['میکروفون', '🎤'],
        'flying_saucer' => ['بشقاب پرنده', '🛸'],
        'racing_car' => ['ماشین مسابقه', '🏎️'],
        'rainbow' => ['رنگین‌کمان', '🌈'],
        'butterfly' => ['پروانه', '🦋'],
        'dragon_face' => ['اژدها', '🐲'],
        'snake' => ['مار', '🐍'],
        'eagle' => ['عقاب', '🦅'],
        'dolphin' => ['دلفین', '🐬'],
        'shark' => ['کوسه', '🦈'],
        'unicorn' => ['یونیکورن', '🦄'],
        'crescent_moon' => ['ماه', '🌙'],
        'castle' => ['قلعه', '🏰'],
        'pirate_flag' => ['پرچم دزدان دریایی', '🏴‍☠️'],
        'chequered_flag' => ['پرچم شطرنجی', '🏁'],
        'bottle_with_popping_cork' => ['شامپاین', '🍾'],
        'video_game' => ['دسته بازی', '🎮'],
        'headphone' => ['هدفون', '🎧'],
        'lollipop' => ['آبنبات', '🍭'],
        'moai' => ['موآی', '🗿'],
        'octopus' => ['هشت‌پا', '🐙'],
    ];

    /**
     * @param bool $animated false when ffmpeg is unavailable: the same designs are served as static emoji
     * @return ArtTemplate[] in display order
     */
    public static function all(string $dir, string $cacheDir, bool $animated = true): array
    {
        $fx = fn (string $effect) => $animated ? $effect : 'none';
        $out = [];
        foreach (self::LABEL as $file => [$title, $emoji, $effect, $box]) {
            $out[] = new ArtTemplate('al_' . $file, $title, self::CAT_TREND, $emoji, $fx($effect), $file, ArtTemplate::LABEL, $box, $dir, $cacheDir);
        }
        foreach (self::SIGN as $file => [$title, $emoji]) {
            $out[] = new ArtTemplate('as_' . $file, $title, self::CAT_SIGN, $emoji, $fx('peek'), $file, ArtTemplate::SIGN, [], $dir, $cacheDir);
        }
        foreach (self::BANNER as $file => [$title, $emoji]) {
            $out[] = new ArtTemplate('ab_' . $file, $title, self::CAT_RIBBON, $emoji, $fx('hover'), $file, ArtTemplate::BANNER, [], $dir, $cacheDir);
        }
        return $out;
    }

    /** @return string[] artwork files the catalog needs */
    public static function files(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::LABEL), array_keys(self::SIGN), array_keys(self::BANNER))));
    }
}
