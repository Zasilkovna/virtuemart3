<?php

namespace VirtueMartModelZasilkovna;

class ConfigSessionStorage extends SessionStorage
{
    private const ID = 'fromPost';
    private const KEY = 'formValues';

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->get(self::ID, self::KEY);
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    public function write(mixed $data): void
    {
        $this->set($data, self::ID, self::KEY);
    }

    public function flush(): void
    {
        $this->remove(self::ID, self::KEY);
    }
}
