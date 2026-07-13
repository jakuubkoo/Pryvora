<?php

declare(strict_types=1);

namespace App\Integration;

/**
 * Describes the fields a provider needs in order to connect.
 * The frontend renders any provider's connect dialog from this, so adding a
 * provider requires no frontend change.
 */
final readonly class CredentialForm
{
    /**
     * @param list<array{name: string, label: string, type: string, required: bool, help?: string}> $fields
     */
    public function __construct(private array $fields)
    {
    }

    /**
     * @return list<array{name: string, label: string, type: string, required: bool, help?: string}>
     */
    public function get_fields(): array
    {
        return $this->fields;
    }

    /**
     * @return list<string>
     */
    public function get_required_field_names(): array
    {
        $names = [];

        foreach ($this->fields as $field) {
            if ($field['required']) {
                $names[] = $field['name'];
            }
        }

        return $names;
    }
}
