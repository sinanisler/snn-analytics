<?php

namespace IAWP\Models;

/** @internal */
class Form
{
    protected $row;
    protected $form_title;
    protected $submissions;
    public function __construct($row)
    {
        $this->row = $row;
        $this->form_title = $row->form_title;
        $this->submissions = \intval($row->submissions);
    }
    public function form_title() : string
    {
        return $this->form_title;
    }
    public function submissions() : int
    {
        return $this->submissions;
    }
}
