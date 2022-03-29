<?php namespace DataTypes;

/**
 * Value type class expressing a date string in 'Y-m-d' format.
 * @package DataTypes
 */
class DateString {
    private $date_string;

    /**
     * @param $date_string Any string that represents a date and is readable by PHP's strtotime function.
     * @throws \Exception
     */
    function __construct($date_string)
    {
        $this->set($date_string);
    }

    public function set($date_string)
    {
        $time = strtotime($date_string);
        if ($time !== false) {
            $this->date_string = date("Y-m-d", $time);
        }
        else throw new \Exception(__CLASS__ . ": Provided value ($date_string) is not a valid date!");
    }

    public function get()
    {
        return $this->date_string;
    }

    public function __invoke()
    {
        return $this->date_string;
    }
    
    public function __toString()
    {
        return $this->get();
    }
}