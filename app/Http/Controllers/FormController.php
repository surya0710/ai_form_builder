<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Models\Form;
use App\Services\Form\FormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function store(StoreFormRequest $request, FormService $forms): RedirectResponse
    {
        $this->authorize('create', Form::class);
        $form = $forms->create($request->user(), $request->validated());

        return redirect()->route('forms.builder', $form)->with('status', 'Form created. Add fields in the builder.');
    }

    public function update(UpdateFormRequest $request, Form $form, FormService $forms): RedirectResponse
    {
        $this->authorize('update', $form);
        $forms->update($form, $request->validated());

        return redirect()->route('forms.show', $form)->with('status', 'Form updated.');
    }

    public function destroy(Request $request, Form $form, FormService $forms): RedirectResponse
    {
        $this->authorize('delete', $form);
        $forms->delete($form);

        return redirect()->route('forms.index')->with('status', 'Form deleted.');
    }

    public function publish(Request $request, Form $form, FormService $forms): RedirectResponse
    {
        $this->authorize('update', $form);
        $forms->publish($form);

        return back()
            ->with('status', 'Form published.')
            ->with('show_publish_modal', true);
    }

    public function archive(Request $request, Form $form, FormService $forms): RedirectResponse
    {
        $this->authorize('update', $form);
        $forms->archive($form);

        return back()->with('status', 'Form archived.');
    }
}
