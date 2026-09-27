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
        self::CAT_TREND => '🔥 Trending',
        self::CAT_SIGN => '🐾 Sign holders',
        self::CAT_RIBBON => '🎀 Ribbons',
    ];

    /** file => [title, emoji, effect, box on the object [cx, cy, w, h, angle?] in artwork coords] */
    private const LABEL = [
        'trophy' => ['Trophy', '🏆', 'shine', [0.5, 0.30, 0.52, 0.24]],
        'coin' => ['Coin', '🪙', 'flip', [0.5, 0.5, 0.6, 0.34]],
        'money_bag' => ['Money bag', '💰', 'wiggle', [0.5, 0.64, 0.62, 0.3]],
        'gem_stone' => ['Gem stone', '💎', 'shine', [0.5, 0.5, 0.62, 0.28]],
        'crown' => ['Crown', '👑', 'shine', [0.5, 0.64, 0.7, 0.22]],
        'ring' => ['Ring', '💍', 'shine', [0.5, 0.7, 0.6, 0.2]],
        'rocket' => ['Rocket', '🚀', 'float', [0.5, 0.5, 0.56, 0.2, -45]],
        'fire' => ['Fire', '🔥', 'pulse', [0.5, 0.64, 0.6, 0.3]],
        'wrapped_gift' => ['Wrapped gift', '🎁', 'shake', [0.5, 0.66, 0.62, 0.28]],
        'birthday_cake' => ['Birthday cake', '🎂', 'pulse', [0.5, 0.64, 0.7, 0.24]],
        '1st_place_medal' => ['1st place medal', '🥇', 'shine', [0.5, 0.64, 0.48, 0.3]],
        'red_heart' => ['Red heart', '❤️', 'beat', [0.5, 0.47, 0.66, 0.32]],
        'sparkling_heart' => ['Sparkling heart', '💖', 'beat', [0.54, 0.52, 0.6, 0.3]],
        'heart_with_ribbon' => ['Heart with ribbon', '💝', 'beat', [0.5, 0.56, 0.56, 0.28]],
        'shield' => ['Shield', '🛡️', 'shine', [0.5, 0.48, 0.56, 0.3]],
        'slot_machine' => ['Slot machine', '🎰', 'blink', [0.47, 0.42, 0.56, 0.18]],
        'dollar_banknote' => ['Dollar banknote', '💵', 'wiggle', [0.5, 0.5, 0.72, 0.26]],
        'credit_card' => ['Credit card', '💳', 'shine', [0.5, 0.6, 0.76, 0.22]],
        'handbag' => ['Handbag', '👜', 'wiggle', [0.5, 0.64, 0.64, 0.26]],
        'shopping_bags' => ['Shopping bags', '🛍️', 'wiggle', [0.42, 0.66, 0.46, 0.24]],
        'bubble_tea' => ['Bubble tea', '🧋', 'wiggle', [0.5, 0.66, 0.5, 0.24]],
        'hot_beverage' => ['Hot beverage', '☕', 'float', [0.45, 0.56, 0.52, 0.26]],
        'laptop' => ['Laptop', '💻', 'blink', [0.5, 0.4, 0.66, 0.26]],
        'mobile_phone' => ['Mobile phone', '📱', 'shake', [0.5, 0.5, 0.5, 0.3]],
        'soccer_ball' => ['Soccer ball', '⚽', 'float', [0.5, 0.5, 0.7, 0.3]],
        'basketball' => ['Basketball', '🏀', 'float', [0.5, 0.5, 0.7, 0.3]],
        'glowing_star' => ['Glowing star', '🌟', 'pulse', [0.5, 0.56, 0.46, 0.24]],
        'peach' => ['Peach', '🍑', 'beat', [0.5, 0.56, 0.62, 0.3]],
        'cookie' => ['Cookie', '🍪', 'wiggle', [0.5, 0.5, 0.68, 0.3]],
        'doughnut' => ['Doughnut', '🍩', 'pulse', [0.5, 0.5, 0.7, 0.3]],
        'crystal_ball' => ['Crystal ball', '🔮', 'shine', [0.5, 0.42, 0.56, 0.26]],
        'light_bulb' => ['Light bulb', '💡', 'blink', [0.5, 0.36, 0.52, 0.24]],
        'bomb' => ['Bomb', '💣', 'shake', [0.45, 0.58, 0.6, 0.3]],
        'nazar_amulet' => ['Nazar amulet', '🧿', 'pulse', [0.5, 0.5, 0.62, 0.3]],
        'top_hat' => ['Top hat', '🎩', 'wiggle', [0.5, 0.64, 0.64, 0.2]],
        'billed_cap' => ['Billed cap', '🧢', 'wiggle', [0.52, 0.42, 0.5, 0.22]],
        'teddy_bear' => ['Teddy bear', '🧸', 'wiggle', [0.5, 0.68, 0.44, 0.22]],
        'balloon' => ['Balloon', '🎈', 'float', [0.55, 0.34, 0.5, 0.26]],
        'alarm_clock' => ['Alarm clock', '⏰', 'shake', [0.5, 0.56, 0.52, 0.22]],
        'chart_increasing' => ['Chart increasing', '📈', 'shine', [0.5, 0.5, 0.66, 0.26]],
        'pile_of_poo' => ['Pile of poo', '💩', 'wiggle', [0.5, 0.72, 0.6, 0.22]],
        'four_leaf_clover' => ['Four leaf clover', '🍀', 'wiggle', [0.5, 0.45, 0.6, 0.28]],
        'mushroom' => ['Mushroom', '🍄', 'pulse', [0.5, 0.34, 0.66, 0.24]],
        'collision' => ['Collision', '💥', 'pulse', [0.5, 0.5, 0.56, 0.28]],
    ];

    /** file => [title, emoji] — character peeking over a sign it holds (animated: peek) */
    private const SIGN = [
        'cat_face' => ['Cat face', '🐱'],
        'dog_face' => ['Dog face', '🐶'],
        'frog' => ['Frog', '🐸'],
        'panda' => ['Panda', '🐼'],
        'bear' => ['Bear', '🐻'],
        'fox' => ['Fox', '🦊'],
        'rabbit_face' => ['Rabbit face', '🐰'],
        'tiger_face' => ['Tiger face', '🐯'],
        'monkey_face' => ['Monkey face', '🐵'],
        'pig_face' => ['Pig face', '🐷'],
        'koala' => ['Koala', '🐨'],
        'hamster' => ['Hamster', '🐹'],
        'lion' => ['Lion', '🦁'],
        'wolf' => ['Wolf', '🐺'],
        'alien' => ['Alien', '👽'],
        'robot' => ['Robot', '🤖'],
        'skull' => ['Skull', '💀'],
        'clown_face' => ['Clown face', '🤡'],
        'smiling_face_with_sunglasses' => ['Smiling face with sunglasses', '😎'],
        'money-mouth_face' => ['Money-mouth face', '🤑'],
        'star-struck' => ['Star-struck', '🤩'],
        'face_with_tears_of_joy' => ['Face with tears of joy', '😂'],
        'cowboy_hat_face' => ['Cowboy hat face', '🤠'],
        'smiling_cat_with_heart-eyes' => ['Smiling cat with heart-eyes', '😻'],
        'see-no-evil_monkey' => ['See-no-evil monkey', '🙈'],
        'smiling_face_with_hearts' => ['Smiling face with hearts', '🥰'],
        'ghost' => ['Ghost', '👻'],
        'baby_chick' => ['Baby chick', '🐤'],
        'penguin' => ['Penguin', '🐧'],
        'ninja' => ['Ninja', '🥷'],
    ];

    /** file => [title, emoji] — object floating above a name banner (animated: hover) */
    private const BANNER = [
        'rose' => ['Rose', '🌹'],
        'tulip' => ['Tulip', '🌷'],
        'bouquet' => ['Bouquet', '💐'],
        'sunflower' => ['Sunflower', '🌻'],
        'cherry_blossom' => ['Cherry blossom', '🌸'],
        'magic_wand' => ['Magic wand', '🪄'],
        'sparkles' => ['Sparkles', '✨'],
        'high_voltage' => ['High voltage', '⚡'],
        'party_popper' => ['Party popper', '🎉'],
        'crossed_swords' => ['Crossed swords', '⚔️'],
        'guitar' => ['Guitar', '🎸'],
        'microphone' => ['Microphone', '🎤'],
        'flying_saucer' => ['Flying saucer', '🛸'],
        'racing_car' => ['Racing car', '🏎️'],
        'rainbow' => ['Rainbow', '🌈'],
        'butterfly' => ['Butterfly', '🦋'],
        'dragon_face' => ['Dragon face', '🐲'],
        'snake' => ['Snake', '🐍'],
        'eagle' => ['Eagle', '🦅'],
        'dolphin' => ['Dolphin', '🐬'],
        'shark' => ['Shark', '🦈'],
        'unicorn' => ['Unicorn', '🦄'],
        'crescent_moon' => ['Crescent moon', '🌙'],
        'castle' => ['Castle', '🏰'],
        'pirate_flag' => ['Pirate flag', '🏴‍☠️'],
        'chequered_flag' => ['Chequered flag', '🏁'],
        'bottle_with_popping_cork' => ['Bottle with popping cork', '🍾'],
        'video_game' => ['Video game', '🎮'],
        'headphone' => ['Headphone', '🎧'],
        'lollipop' => ['Lollipop', '🍭'],
        'moai' => ['Moai', '🗿'],
        'octopus' => ['Octopus', '🐙'],
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
