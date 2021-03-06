<?php
namespace Electro\Database\Lib;

use Electro\Exceptions\Fatal\FileNotFoundException;
use ErrorException;

class CsvUtil
{
  /**
   * Loads CSV-formatted data from a file, parses it and returns an array of arrays.
   *
   * Data should be comma-delimited and string may be enclosed on double quotes.
   *
   * @param string            $filename The file path.
   * @param array|string|null $columns  Either:<ul>
   *                                    <li> an array of column names,
   *                                    <li> a string of comma-delimited column names,
   *                                    <li> null (or ommited) to read column names from the first row of data.
   *                                    </ul>
   * @return array The loaded data.
   * @throws FileNotFoundException
   */
  static function loadCSV ($filename, $columns = null)
  {
    $handle = @fopen ($filename, 'rb', true);
    if (!$handle)
      throw new FileNotFoundException($filename);
    return self::loadCsvFromStream ($handle, $columns);
  }

  /**
   * Loads CSV-formatted data from a stream, parses it and returns an array of arrays.
   *
   * It closes the stream before returning.
   * Data should be comma-delimited and string may be enclosed on double quotes.
   *
   * @param resource          $handle    An opened stream.
   * @param array|string|null $columns   Either:<ul>
   *                                     <li> an array of column names,
   *                                     <li> a string of comma-delimited (and possibly quoted) column names,
   *                                     <li> null (or ommited) to read column names from the first row of data.
   *                                     </ul>
   * @param string            $delimiter The character used for separating fields. If not specified, it will be
   *                                     auto-decteded, if possible. If it's not possible, a comma will be used.
   * @return array The loaded data.
   */
  static function loadCsvFromStream ($handle, $columns = null, $delimiter = '')
  {
    if (is_null ($columns))
      $columns = removeBOM (fgets ($handle));

    if (is_string ($columns)) {
      if (!$delimiter)
        $delimiter = self::autoDetectSeparator ($columns);
      $columns = map (explode ($delimiter, $columns), function ($col) { return preg_replace ("/^[\\s\"\t']|[\\s\"\t']$/", '', $col);});
    }
    else if (!$delimiter)
      $delimiter = ',';

    // use fgetcsv which tends to work better than str_getcsv in some cases
    $data = [];
    $i    = 0;
    $row  = '';
    try {
      while ($row = fgetcsv ($handle, null, $delimiter, '"')) {
        ++$i;
        $data[] = array_combine ($columns, $row);
      }
      fclose ($handle);
    }
    catch (ErrorException $e) {
      echo "\nInvalid row #$i\n\nColumns:\n";
      var_export ($columns);
      echo "\n\nRow:\n";
      var_export ($row);
      echo "\n";
      exit (1);
    }
    return $data;
  }

  static function autoDetectSeparator ($line)
  {
    if (strpos ($line, ';') !== false) return ';';
    if (strpos ($line, "\t") !== false) return "\t";
    return ',';
  }

  /**
   * Parses CSV-formatted data from a string and returns it as an array of arrays.
   *
   * Data should be comma-delimited and string may be enclosed on double quotes.
   *
   * @param array|string|null $columns Either:<ul>
   *                                   <li> an array of column names,
   *                                   <li> a string of comma-delimited column names,
   *                                   <li> null (or ommited) to read column names from the first row of data.
   *                                   </ul>
   * @param string            $csv     The CSV data.
   * @return array The loaded data.
   */
  static function parseCSV ($columns, $csv)
  {
// Use an I/O stream instead of an actual file.
    $handle = fopen ('php://temp/myCSV', 'w+b');

// Write all the data to it
    fwrite ($handle, $csv);

// Rewind for reading
    rewind ($handle);

    return self::loadCsvFromStream ($handle, $columns);
  }

}
