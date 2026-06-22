<?php

define(
    "RETURN_ERROR_ALFRED",
    new AlfredSF(
        items: [
            new AlfredSFItem(
                title: "Unable to Load Results",
                subtitle: "Open the debugger and try again",
                valid: false,
            ),
        ],
    ),
);

class AlfredSFBase implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }
}
/**
 * The Script Filter JSON Format Type defined.
 * @author fradeet
 * @link https://www.alfredapp.com/help/workflows/inputs/script-filter/json/ Official Alfred Documents
 */
class AlfredSF extends AlfredSFBase
{
    public function __construct(
        public array $items, // TODO type declaration
        public ?array $variables = null,
        public ?string $rerun = null,
        public ?AlfredSFCache $cache = null,
        public ?bool $skipknowledge = null,
    ) {}
}

class AlfredSFItem extends AlfredSFBase
{
    public function __construct(
        public string $title,
        public null|array|string $action = null,
        public null|string|array $arg = null,
        public ?string $autocomplete = null,
        public ?AlfredSFItemIcon $icon = null,
        public ?string $match = null,
        public ?array $mods = null, // TODO type declaration
        public ?string $quicklookurl = null,
        public ?string $subtitle = null,
        public ?AlfredSFItemType $type = null,
        public ?AlfredSFItemText $text = null,
        public ?string $uid = null,
        public ?array $variables = null,
        public ?bool $valid = null,
    ) {}
}

class AlfredSFCache extends AlfredSFBase
{
    public function __construct(
        public int $seconds,
        public ?bool $loosereload = null,
    ) {}
}

class AlfredSFItemText extends AlfredSFBase
{
    public function __construct(
        public string $copy,
        public string $largetype,
    ) {}
}

enum AlfredSFItemType: string
{
    case Default = "default";
    case File = "file";
    case FileSkipcheck = "file:skipcheck";
}

class AlfredSFItemIcon extends AlfredSFBase
{
    public function __construct(
        public string $path,
        public ?string $type = null,
    ) {}
}
