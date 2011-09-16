<?php
/**
 * Read Item STL / GAM Files
 *
 * This is open-source code which you can use for anything you like,
 * I just ask that you please make mention of my contributions
 * and http://www.exoduslabs.ca/ in your source code if you use this!
 *
 * @author Guillaume VanderEst <gui@exoduslabs.ca>
 */

class Item_Reader
{
    public function get_items_data($filename = NULL)
    {
        if ($filename === NULL)
        {
            $filenames = array(
                '../data/mpq/Items_Armor.gam',
                '../data/mpq/Items_Legendary.gam',
                '../data/mpq/Items_Legendary_Other.gam',
                '../data/mpq/Items_Legendary_Weapons.gam',
                '../data/mpq/Items_Other.gam',
                '../data/mpq/Items_Quests_Beta.gam',
                '../data/mpq/Items_Weapons.gam'
            );

            $data = array();
            foreach ($filenames as $filename)
            {
                $data = array_merge($data, $this->get_items_data($filename));
            }
            return $data;
        }

        $fh = fopen($filename, 'r');
        fseek($fh, 0x3ac);

        $output = array();
        $y = 0;

        while (!feof($fh))
        {
            $item = new stdClass;

            $read = fread($fh, 0x100);
            if (trim(substr($read, 0, 1)) == '') { break; }
            
            $y++;

            $item->diablo_id = $read;
            /*
            var_dump("===============================");
            var_dump($item->id);
            var_dump("===============================");
            */
            $fields = array(
                //0 => array('subtype_maybe', 'int'),
                //2 => array('type_maybe_or_quality', 'int'),
                4 => array('ilvl', 'int'),
                8 => array('sockets_max', 'int'),
                10 => array('gold', 'int'),
                12 => array('clvl', 'int'),
                //13 => array('something1', 'int'),
                //14 => array('something2', 'int')
            );

            for ($x = 0; $x < 292 && !feof($fh); $x++)
            {
                $pos = ftell($fh);
                $raw = fread($fh, 4);

                if (empty($raw)) { break; }

                $char = unpack('C', $raw);
                $short = unpack('S', $raw);
                $int = unpack('i', $raw);
                $float = unpack('f', $raw);
                $long = unpack('L', $raw);
                if ($y < 10 && $x < 20)
                /*printf("(@%s:%d) %sint: %s, float: %s\n", 
                    dechex($pos), 
                    $x, 
                    (isset($fields[$x]) ? '[' . $fields[$x][0] . '] ' : ''), 
                    $int[1], 
                    (float)$float[1]
                );*/

                if (isset($fields[$x]))
                {
                    $field = $fields[$x];
                    $field_name = $field[0];
                    $field_type = $field[1];
                    $val = $$field_type;
                    $item->$field_name = $val[1];
                }
            }
            if (!isset($count)) { $count = 0; }
            
            foreach ($item as $key => $value)
            {
                $item->$key = trim($value);
            }
            $output[] = $item;
        }

        return $output;
    }

    public function get_items()
    {
        $fh = fopen('../data/mpq/Items.stl', 'r');
        fseek($fh, 0x002cbd0, SEEK_SET);
        $null_count = 0;
        $char_count = 0;

        $item_id = '';
        $item_name = '';
        $item_notes = '';

        $items = array();

        $buffer = '';
        $item_part = 0;

        // the item data holder
        $item = new stdClass;

        while (!feof($fh))
        {
            $char = fread($fh, 1);

            // if the character is null (or the eof 0A char Blizzard uses), it's terminating a string..
            if (ord($char) == 0) 
            {
                $null_count++;
                if ($null_count == 1)
                {
                    $item_part++;

                // if the item is in part 2 and there is a null count >= 9, reset to 1
                } elseif ($item_part == 2 && $null_count >= 9) { 

                    $item_part = 1;
                    continue;
                }
            } else {
                $char_count++;
                $buffer .= $char;
            }

            // if the string was the third part or the null count is >= 9 and was in second part, parse the item and return to first part
            if ($null_count == 1 && $item_part > 3 || $char_count == 1 && $item_part == 3 && $null_count >= 9 && !empty($item->diablo_id))
            {
                // append item and restart process
                // clean the string up (nulls and such are very dirty)
                foreach ($item as $key => $value) { $item->$key = trim($value); }

                // debug
                $this->add_deduced_data($item);
                $items[] = $item;

                $item = new stdClass;
                $item_part = 1;

                // everything got reset
                if ($char_count == 1)
                {
                    continue;
                }
            }

            switch ($item_part)
            {
                case 1: $item->diablo_id = $buffer; break;
                case 2: $item->name = $buffer; break;
                case 3: $item->notes = $buffer; break;
                default: throw new Exception('Reached invalid item_part setting'); exit();
            }

            // set the opposite counter to zero now and clear buffer
            if (ord($char) == 0)
            {
                $char_count = 0;
                $buffer = '';
            } else {
                $null_count = 0;
            }
        }
        return $items;
    }

    /**
     * Modify the item for information deduced from its name/ID
     * @param object $item
     * @return void
     */
    public function add_deduced_data(&$item)
    {
        // discern quality
        $item->quality = 'superior';
        if (preg_match('/Unique/i', $item->diablo_id))
        {
            $item->quality = 'legendary';
        } elseif (preg_match('/Rare/i', $item->diablo_id)) {
            $item->quality = 'rare';
        }

        // discern type
        if (preg_match('/Pants/i', $item->diablo_id))
        {
            $item->type = 'armor';
            $item->slot = 'pants';
            $item->subtype = $item->slot;
        } elseif (preg_match('/WizardHat/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'helm';
            $item->subtype = 'wizardhat';
        } elseif (preg_match('/(Hood|Helm)/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'helm';
            $item->subtype = $item->slot;
        } elseif (preg_match('/(ChestArmor|Chest)/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'chest';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Gloves/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'gloves';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Cloak/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'cloak';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Boots?/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'boots';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Shoulders?/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'shoulders';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Belt/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'belt';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Bracers?/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'bracers';
            $item->subtype = $item->slot;
        } elseif (preg_match('/Shield/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->slot = 'offhand';
            $item->subtype = 'shield';
        } elseif (preg_match('/Quiver/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'quiver';
            $item->slot = 'offhand';
        } elseif (preg_match('/Orb/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'orb';
            $item->slot = 'offhand';
        } elseif (preg_match('/SpiritStone/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'spiritstone';
            $item->slot = 'amulet';
        } elseif (preg_match('/VoodooMask/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'voodoomask';
            $item->slot = 'helm';
        } elseif (preg_match('/Mojo/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'mojo';
            $item->slot = 'amulet';
        } elseif (preg_match('/Axe_1H/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'axe';
            $item->slot = 'onehand';
        } elseif (preg_match('/Axe_2H/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'axe';
            $item->slot = 'twohand';
        } elseif (preg_match('/(Rod|Scepter)/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'combatstaff';
            $item->slot = 'onehand';
        } elseif (preg_match('/CombatStaff_2H/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'combatstaff';
            $item->slot = 'twohand';
        } elseif (preg_match('/Dagger/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'dagger';
            $item->slot = 'onehand';
        } elseif (preg_match('/(FistWeapon_1H|Fist)/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'fistweapon';
            $item->slot = 'onehand';
        } elseif (preg_match('/(Club|Mace_1H)/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'mace';
            $item->slot = 'onehand';
        } elseif (preg_match('/Mace_2H/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'mace';
            $item->slot = 'twohand';
        } elseif (preg_match('/Polearm/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'polearm';
            $item->slot = 'twohand';
        } elseif (preg_match('/(BladeOf|WarSword|Sword_1H)/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'sword';
            $item->slot = 'onehand';
        } elseif (preg_match('/Sword_2H/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'sword';
            $item->slot = 'twohand';
        } elseif (preg_match('/Bow/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'bow';
            $item->slot = 'twohand';
        } elseif (preg_match('/Spear/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'spear';
            $item->slot = 'twohand';
        } elseif (preg_match('/ThrownWeapon/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'thrownweapon';
            $item->slot = 'onehand';
        } elseif (preg_match('/ThrowingAxe/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'thrownweapon';
            $item->slot = 'onehand';
        } elseif (preg_match('/Staff/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'staff';
            $item->slot = 'twohand';
        } elseif (preg_match('/Wand/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'wand';
            $item->slot = 'mainhand';
        } elseif (preg_match('/(MightyWeapon1H|Mighty_1H)/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'mighty';
            $item->slot = 'onehand';
        } elseif (preg_match('/(MightyWeapon2H|Mighty_2H)/i', $item->diablo_id)) {
            $item->type = 'weapon';
            $item->subtype = 'mighty';
            $item->slot = 'twohand';
        } elseif (preg_match('/Glyph/i', $item->diablo_id)) {
            $item->type = 'glyph';
            $item->subtype = '';
            $item->slot = 'glyph';
        } elseif (preg_match('/Glyph/i', $item->diablo_id)) {
            $item->type = 'glyph';
            $item->subtype = '';
            $item->slot = 'glyph';
        } elseif (preg_match('/Junk/i', $item->diablo_id)) {
            $item->type = 'junk';
            $item->subtype = '';
            $item->slot = 'inventory';
            $item->quality = 'inferior';
        } elseif (preg_match('/Ammo/i', $item->diablo_id)) {
            $item->type = 'ammo';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/Ring/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'ring';
            $item->slot = 'ring';
        } elseif (preg_match('/ManaPotion/i', $item->diablo_id)) {
            $item->type = 'potion';
            $item->subtype = 'mana';
            $item->slot = 'inventory';
        } elseif (preg_match('/HealthPotion/i', $item->diablo_id)) {
            $item->type = 'potion';
            $item->subtype = 'health';
            $item->slot = 'inventory';
        } elseif (preg_match('/Amulet/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'amulet';
            $item->slot = 'amulet';
        } elseif (preg_match('/Charm/i', $item->diablo_id)) {
            $item->type = 'charm';
            $item->subtype = '';
            $item->slot = 'charm';
        } elseif (preg_match('/Dye/i', $item->diablo_id)) {
            $item->type = 'dye';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/(Lore|Journal)/i', $item->diablo_id)) {
            $item->type = 'lore';
            $item->subtype = '';
            $item->slot = 'inventory';
            $item->quality = 'lore';
        } elseif (preg_match('/(Scroll|VialOf|RespecTome|Elixir|Potion)/i', $item->diablo_id)) {
            $item->type = '';
            $item->subtype = 'respec';
            $item->slot = 'inventory';
        } elseif (preg_match('/Event/i', $item->diablo_id)) {
            $item->type = 'quest';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/Templar_Special/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'templar';
            $item->slot = 'special';
        } elseif (preg_match('/Enchantress_Special/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'enchantress';
            $item->slot = 'special';
        } elseif (preg_match('/Scoundrel_Special/i', $item->diablo_id)) {
            $item->type = 'armor';
            $item->subtype = 'scoundrel';
            $item->slot = 'special';
        } elseif (preg_match('/Seal/i', $item->diablo_id)) {
            $item->type = 'quest';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/HealthGlobe/i', $item->diablo_id)) {
            $item->type = 'globe';
            $item->subtype = 'health';
            $item->slot = 'ground';
        } elseif (preg_match('/RejuvenationPotion/i', $item->diablo_id)) {
            $item->type = 'potion';
            $item->subtype = 'rejuvenation';
            $item->slot = 'inventory';
        } elseif (preg_match('/RestorationPotion/i', $item->diablo_id)) {
            $item->type = 'potion';
            $item->subtype = 'restoration';
            $item->slot = 'inventory';
        } elseif (preg_match('/Potion/i', $item->diablo_id)) {
            $item->type = 'potion';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/BlacksmithsTome/i', $item->diablo_id)) {
            $item->type = 'tome';
            $item->subtype = 'blacksmith';
            $item->slot = 'inventory';
        } elseif (preg_match('/Page/i', $item->diablo_id)) {
            $item->type = 'tomepage';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/TownPortalStone/i', $item->diablo_id)) {
            $item->type = 'stone';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/^Gold[1234]?$/i', $item->diablo_id)) {
            $item->type = 'gold';
            $item->subtype = '';
            $item->slot = 'currency';
        } elseif (preg_match('/Enchantment/i', $item->diablo_id)) {
            $item->type = 'enchant';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/^Rune([0-9]+)$/i', $item->diablo_id, $matches)) {
            $item->type = 'rune';
            $item->subtype = $matches[1];
            $item->slot = 'inventory';
        } elseif (preg_match('/^Gambling/i', $item->diablo_id, $matches)) {
            $item->type = 'gambling';
            $item->subtype = '';
            $item->slot = 'gambling';
        } elseif (preg_match('/(Amethyst|Diamond|Emerald|Ruby|Sapphire|Topaz|Skull)/i', $item->diablo_id, $matches)) {
            $item->type = 'gem';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/^CraftingPlan_Mystic_/i', $item->diablo_id)) {
            $item->type = 'craftingplan';
            $item->subtype = 'mystic';
            $item->slot = 'inventory';
        } elseif (preg_match('/^CraftingPlan_Smith_/i', $item->diablo_id)) {
            $item->type = 'craftingplan';
            $item->subtype = 'blacksmith';
            $item->slot = 'inventory';
        } elseif (preg_match('/^Crafting_/i', $item->diablo_id, $matches)) {
            $item->type = 'crafting';
            $item->subtype = '';
            $item->slot = 'inventory';
        } elseif (preg_match('/^(Runestone|Spellrune)/i', $item->diablo_id, $matches)) {
            $item->type = 'runestone';
            if (preg_match('/Crimson/', $item->name)) { $item->subtype = 'red'; }
            elseif (preg_match('/Indigo/', $item->name)) { $item->subtype = 'blue'; }
            elseif (preg_match('/Golden/', $item->name)) { $item->subtype = 'yellow'; }
            elseif (preg_match('/Alabaster/', $item->name)) { $item->subtype = 'white'; }
            elseif (preg_match('/Obsidian/', $item->name)) { $item->subtype = 'black'; }
            elseif (preg_match('/Unattuned/', $item->name)) { $item->subtype = 'unattuned'; }
            elseif (preg_match('/(Life Steal|Health|Extension|Speed|Impact)/', $item->name)) { $item->subtype = ''; }
            else { var_dump($item->name); exit('rune color not fetched'); }
            $item->slot = 'inventory';
            $item->quality = 'runestone';

        // assume everything else is a quest item
        } else {
            $item->type = 'quest';
            $item->subtype = '';
            $item->slot = 'inventory';
            $item->quality = 'quest';
        }
    }
}

error_reporting(E_ALL|E_NOTICE);
ini_set('display_errors', 1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__) . '/../html/';
require_once('../html/exo/init.php');

$model = new Horadric_Database_Model();
$reader = new Item_Reader();

// get the names and notes of items
$datas = $reader->get_items();
foreach ($datas as $row)
{
    $item = $model->get_item_by_diablo_id($row->diablo_id);

    // if the item doesn't exist, create it
    if (!$item)
    {
        if (!$model->add_item($row))
        {
            exit('Unable to add item on basic attempt');
        }
    } else {
        if (!$model->edit_item($item->id, $row))
        {
            exit('Unable to edit item on basic attempt');
        }
    }
}

// get the stats on each of the items
$datas = $reader->get_items_data();
foreach ($datas as $row)
{
    $item = $model->get_item_by_diablo_id($row->diablo_id);

    // if the item doesn't exist
    if (!$item)
    {
        printf("%s not found for detailed data\n", $row->diablo_id);
        continue;
    }

    if (!$model->edit_item($item->id, $row))
    {
        exit('Unable to edit item on complex data attempt');
    }
}

