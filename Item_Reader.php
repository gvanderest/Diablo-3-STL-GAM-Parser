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
    public function get_items()
    {
        $fh = fopen('../data/mpq/Items.stl', 'r');
        fseek($fh, 0x002cbd0, SEEK_SET);
        $null_count = 0;
        $char_count = 0;
        $part = 0;

        $item_id = '';
        $item_name = '';
        $item_notes = '';

        $items = array();

        $buffer = '';
        $item_part = 0;


        while (TRUE)
        {
            $read = fread($fh, 4096);

            // for each character
            for ($x = 0; $x < strlen($read); $x++)
            {
                $char = substr($read, $x, 1);

                // there are null characters coming up..
                if (ord($char) == 0 || feof($fh)) 
                { 
                    // if null_count is zero, we just ended a string
                    if ($null_count == 0)
                    {
                        // if there's an underscore, it's an ID; otherwise, it's the next part
                        if (strpos($buffer, '_') !== FALSE || substr($buffer, 0, 5) == 'Glyph')
                        {
                            if (!empty($item_id))
                            {
                                // append item
                                $item = new stdClass;
                                $item->id = $item_id;
                                $item->name = $item_name;
                                $item->notes = $item_notes;

                                foreach ($item as $key => $value)
                                {
                                    $item->$key = str_replace("\n", '', $value);
                                }

                                $items[] = $item;
                    
                                // append item and start again
                                $item_part = 0;

                                $item_id = '';
                                $item_name = '';
                                $item_notes = '';
                            }

                        } else {
                            if ($char_count > 0)
                            {
                                $item_part++;
                                if ($item_part > 2) { $item_part = 0; }
                            }
                        }
            
                        // store the information appropriately            
                        switch ($item_part)
                        {
                            case 0: $item_id = $buffer; break;
                            case 1: $item_name = $buffer; break;
                            case 2: $item_notes = $buffer; break;
                        }
                    }

                    $null_count++; 

                    if (!feof($fh))
                    {
                        continue; 
                    }
                }
                elseif ($null_count > 0) 
                { 
                    $buffer = '';
                    $null_count = 0;
                    $part += 1;
                    $char_count++;
                }
                $buffer .= $char;
            }

            // if end of file, break
            if (feof($fh))
            {
                break;
            }
        }

        return $items;
    }
}

$reader = new Item_Reader();
$items = $reader->get_items();
print(count($items));
foreach ($items as $item)
{
    printf("ID: %s, Name: %s, Notes: %s\n", $item->id, $item->name, $item->notes);
}

