<?php
namespace Dominservice\LaravelCms\Enums\Concerns;

trait HasLabel
{
    public function label(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        $key = "enums.$class." . $this->name;
        $translated = __($key);
        return $translated !== $key ? $translated : ucfirst(str_replace('_', ' ', (string)$this->value));
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
