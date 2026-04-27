<?php

namespace Idei\Usim;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\TableRow;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Form;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Split;

/**
 * UI component factory
 *
 * Provides static factory methods to instantiate UI component builders.
 * Each builder supports a fluent API for configuration and composition.
 */
class UI
{
    /**
     * Create a new button component
     *
     * @param string|null $name an optional semantic name for the button
     * @return Button
     */
    public static function button(?string $name = null): Button
    {
        return new Button($name);
    }

    /**
     * Create a new label component
     *
     * @param string|null $name an optional semantic name for the label
     * @return Label
     */
    public static function label(?string $name = null): Label
    {
        return new Label($name);
    }

    /**
     * Create a new table component
     *
     * @param string|null $name The optional semantic name for the table
     * @param int $rows Number of data rows (0 for dynamic table)
     * @param int $cols Number of columns (0 for dynamic table)
     * @return Table
     */
    public static function table(?string $name = null, int $rows = 0, int $cols = 0): Table
    {
        return new Table($name, $rows, $cols);
    }

    /**
     * Create a new table row component
     *
     * @param Table $table The parent table this row belongs to
     * @param string|null $name The optional semantic name for the row
     * @return TableRow
     */
    public static function tableRow(Table $table, ?string $name = null): TableRow
    {
        return new TableRow($table, $name);
    }

    /**
     * Create a new input component
     *
     * @param string|null $name The optional semantic name for the input
     * @return Input
     */
    public static function input(?string $name = null): Input
    {
        return new Input($name);
    }

    /**
     * Create a new select component
     *
     * @param string|null $name The optional semantic name for the select
     * @return Select
     */
    public static function select(?string $name = null): Select
    {
        return new Select($name);
    }

    /**
     * Create a new checkbox component
     *
     * @param string|null $name The optional semantic name for the checkbox
     * @return Checkbox
     */
    public static function checkbox(?string $name = null): Checkbox
    {
        return new Checkbox($name);
    }

    /**
     * Create a new form component
     *
     * @param string|null $name The optional semantic name for the form
     * @return Form
     */
    public static function form(?string $name = null): Form
    {
        return new Form($name);
    }

    /**
     * Create a new container component
     *
     * @param string|null $name The optional semantic name for the container
     * @return Container
     */
    public static function container(?string $name = null, ?string $context = null): Container
    {
        return new Container($name, $context);
    }

    /**
     * Create a new menu dropdown component
     *
     * @param string $name The semantic name for the menu
     * @return MenuDropdown
     */
    public static function menuDropdown(string $name): MenuDropdown
    {
        return new MenuDropdown($name);
    }

    /**
     * Create a new card component
     *
     * @param string|null $name The optional semantic name for the card
     * @return Card
     */
    public static function card(?string $name = null): Card
    {
        return new Card($name);
    }

    /**
     * Create a new uploader component
     *
     * @param string|null $name The optional semantic name for the uploader
     * @return Uploader
     */
    public static function uploader(?string $name = null): Uploader
    {
        return new Uploader($name);
    }

    /**
     * Create a new calendar component
     *
     * @param string|null $name The optional semantic name for the calendar
     * @return Calendar
     */
    public static function calendar(?string $name = null): Calendar
    {
        return new Calendar($name);
    }

    /**
     * Create a new carousel component
     *
     * @param string|null $name The optional semantic name for the carousel
     * @return Carousel
     */
    public static function carousel(?string $name = null): Carousel
    {
        return new Carousel($name);
    }

    /**
     * Create a new split container component.
     *
     * @param string|null $name The optional semantic name for the split container
     * @return Split
     */
    public static function split(?string $name = null): Split
    {
        return new Split($name);
    }

    /**
     * Create a new textarea component
     *
     * @param string|null $name The optional semantic name for the textarea
     * @return \Idei\Usim\Components\UIComponent
     */
    public static function textarea(?string $name = null): \Idei\Usim\Components\UIComponent
    {
        $textareaClass = 'Idei\\Usim\\Components\\Textarea';
        return new $textareaClass($name);
    }
}
