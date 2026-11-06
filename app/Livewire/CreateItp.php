<?php

namespace App\Livewire;

use App\Api\GenerationClient;
use App\Livewire\Concerns\CallsApi;
use App\Support\Runtime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The intake: what the personal ITP is about. Submitting starts a generation on itp-server,
 * which always needs a connection (platform.md §6). Property names match the API's fields, so
 * its errors land on them.
 */
#[Layout('layouts.app')]
#[Title('Create a personal ITP')]
class CreateItp extends Component
{
    use CallsApi;

    /**
     * The optional intake fields: sent as null when left empty.
     */
    protected const OPTIONAL = ['q3_phrase', 'q3_protective', 'q4_scene', 'avoid'];

    public string $display_name = '';

    public string $address_style = 'beloved_name';

    public string $q1_dedication = '';

    public string $q2_challenge = '';

    public string $q3_old_story = '';

    public string $q3_phrase = '';

    public string $q3_protective = '';

    public string $q4_vision = '';

    public string $q4_scene = '';

    public string $q5_soulful = '';

    public string $avoid = '';

    public function mount(): void
    {
        $this->display_name = (string) Auth::user()->first_name;
    }

    /**
     * The same rules as the API's, so most mistakes show before the call.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'address_style' => ['required', Rule::in(['beloved_name', 'beloved_one'])],
            'q1_dedication' => ['required', 'string'],
            'q2_challenge' => ['required', 'string'],
            'q3_old_story' => ['required', 'string'],
            'q3_phrase' => ['nullable', 'string'],
            'q3_protective' => ['nullable', 'string'],
            'q4_vision' => ['required', 'string'],
            'q4_scene' => ['nullable', 'string'],
            'q5_soulful' => ['required', 'string'],
            'avoid' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'display_name' => 'name',
            'address_style' => 'way to address you',
            'q1_dedication' => 'dedication',
            'q2_challenge' => 'challenge',
            'q3_old_story' => 'old story',
            'q4_vision' => 'vision',
            'q5_soulful' => 'answer',
        ];
    }

    public function submit(GenerationClient $generations): void
    {
        foreach (array_keys($this->rules()) as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $this->validate();

        $generation = $this->callApi(fn () => $generations->create($this->intake()), 'connection');

        if ($generation !== null) {
            $this->redirectRoute('generations.show', $generation['id']);
        }
    }

    public function render(): View
    {
        return view('livewire.create-itp', ['isMobile' => Runtime::isMobile()]);
    }

    /**
     * @return array<string, string|null>
     */
    protected function intake(): array
    {
        $intake = [];

        foreach (array_keys($this->rules()) as $field) {
            $intake[$field] = in_array($field, self::OPTIONAL, true) && $this->{$field} === '' ? null : $this->{$field};
        }

        return $intake;
    }
}
