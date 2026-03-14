<?php

namespace Tests\Support;

final class UiComponentRef
{
    private UiScenario $scenario;

    private string $name;

    public function __construct(UiScenario $scenario, string $name)
    {
        $this->scenario = $scenario;
        $this->name = $name;
    }

    public function click(array $parameters = []): self
    {
        $this->scenario->click($this->name, $parameters)->assertOk();
        return $this;
    }

    public function expect(string $field)
    {
        $component = $this->scenario->componentData($this->name);
        return expect($component[$field] ?? null);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->scenario->componentData($this->name);
    }
}
