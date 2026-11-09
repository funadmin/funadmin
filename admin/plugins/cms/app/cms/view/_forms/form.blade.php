{{-- 内置表单片段：表单定义只作为数据传入。纯文本一律 {{ }} 转义；只有已经 HtmlSanitizer 清洗的 paragraph / consent html 原样输出。 --}}
<form class="cms-form" id="{{ $uid }}" data-cms-form method="post" enctype="multipart/form-data" action="{{ $action }}" data-network-error="{{ $ui['network'] }}">
<input type="hidden" name="_token" value="{{ $token }}">
<input type="hidden" name="_locale" value="{{ $locale }}">
<input type="hidden" name="_source" value="{{ $source }}">
<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden"><label for="{{ $uid }}--hp">{{ $ui['honeypot'] }}</label><input type="text" id="{{ $uid }}--hp" name="_hp" value="" tabindex="-1" autocomplete="off"></div>
@foreach($form['fields'] as $field)
@php($type = $field['type'])
@if($type === 'divider')
<hr class="cms-form-divider">
@elseif($type === 'paragraph')
<div class="cms-form-paragraph cms-form-field--{{ $field['width'] }}">{!! $field['html'] !!}</div>
@elseif($type === 'hidden')
<input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}"@if($field['from_query']) data-from-query="{{ $field['name'] }}"@endif>
@else
@php($default = $field['default'])
<div class="cms-form-field cms-form-field--{{ $type }} cms-form-field--{{ $field['width'] }}" data-field="{{ $field['name'] }}">
@if(in_array($type, ['radio', 'checkbox'], true))
<fieldset class="cms-form-group"><legend class="cms-form-label">{{ $field['label'] }}@if($field['required'])<span class="cms-form-required" title="{{ $ui['required'] }}">*</span>@endif</legend>
@foreach($field['options'] as $index => $option)
@php($checked = $type === 'radio' ? $default === $option['value'] : in_array($option['value'], (array)$default, true))
<label class="cms-form-option" for="{{ $field['id'] }}-{{ $index }}"><input type="{{ $type }}" id="{{ $field['id'] }}-{{ $index }}" name="{{ $field['name'] }}{{ $type === 'checkbox' ? '[]' : '' }}" value="{{ $option['value'] }}"@if($checked) checked @endif @if($type === 'radio' && $field['required']) required @endif> <span>{{ $option['label'] }}</span></label>
@endforeach
</fieldset>
@elseif($type === 'consent')
<label class="cms-form-consent" for="{{ $field['id'] }}"><input type="checkbox" id="{{ $field['id'] }}" name="{{ $field['name'] }}" value="1"@if($default === true) checked @endif @if($field['required']) required @endif> <span>@if($field['html'] !== ''){!! $field['html'] !!}@else{{ $field['label'] }}@endif</span></label>
@else
<label class="cms-form-label" for="{{ $field['id'] }}">{{ $field['label'] }}@if($field['required'])<span class="cms-form-required" title="{{ $ui['required'] }}">*</span>@endif</label>
@if($type === 'textarea')
<textarea class="cms-form-control" id="{{ $field['id'] }}" name="{{ $field['name'] }}" rows="5" placeholder="{{ $field['placeholder'] }}"@if(isset($field['max'])) maxlength="{{ $field['max'] }}"@endif @if($field['required']) required @endif @if($field['help'] !== '') aria-describedby="{{ $field['id'] }}-help"@endif>{{ $default ?? '' }}</textarea>
@elseif($type === 'select')
<select class="cms-form-control" id="{{ $field['id'] }}" name="{{ $field['name'] }}{{ $field['multiple'] ? '[]' : '' }}"@if($field['multiple']) multiple @endif @if($field['required']) required @endif @if($field['help'] !== '') aria-describedby="{{ $field['id'] }}-help"@endif>
@unless($field['multiple'])<option value="">{{ $field['placeholder'] !== '' ? $field['placeholder'] : $ui['select'] }}</option>@endunless
@foreach($field['options'] as $option)
<option value="{{ $option['value'] }}"@if(in_array($option['value'], (array)$default, true)) selected @endif>{{ $option['label'] }}</option>
@endforeach
</select>
@elseif(in_array($type, ['file', 'image'], true))
<input class="cms-form-control" type="file" id="{{ $field['id'] }}" name="{{ $field['name'] }}{{ $field['max_files'] > 1 ? '[]' : '' }}" accept="{{ $field['accept_attr'] }}"@if($field['max_files'] > 1) multiple @endif @if($field['required']) required @endif @if($field['help'] !== '') aria-describedby="{{ $field['id'] }}-help"@endif>
@else
@php($inputType = ['text' => 'text', 'email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'time' => 'time'][$type])
<input class="cms-form-control" type="{{ $inputType }}" id="{{ $field['id'] }}" name="{{ $field['name'] }}" value="{{ $default ?? '' }}" placeholder="{{ $field['placeholder'] }}"@if($type === 'text' && isset($field['max'])) maxlength="{{ $field['max'] }}"@endif @if(in_array($type, ['number', 'date'], true) && isset($field['min'])) min="{{ $field['min'] }}"@endif @if(in_array($type, ['number', 'date'], true) && isset($field['max'])) max="{{ $field['max'] }}"@endif @if($type === 'number') step="{{ $field['integer'] ? '1' : 'any' }}"@endif @if($type === 'email') autocomplete="email"@elseif($type === 'phone') autocomplete="tel"@endif @if($field['required']) required @endif @if($field['help'] !== '') aria-describedby="{{ $field['id'] }}-help"@endif>
@endif
@endif
@if($field['help'] !== '')<small class="cms-form-help" id="{{ $field['id'] }}-help">{{ $field['help'] }}</small>@endif
<p class="cms-form-error" data-error role="alert" hidden></p>
</div>
@endif
@endforeach
@if($captcha)
<div class="cms-form-field cms-form-captcha" data-field="captcha">
<label class="cms-form-label" for="{{ $uid }}--captcha">{{ $ui['captcha'] }}<span class="cms-form-required" title="{{ $ui['required'] }}">*</span></label>
<div class="cms-form-captcha-row"><input class="cms-form-control" type="text" id="{{ $uid }}--captcha" name="_captcha" required autocomplete="off" inputmode="latin" maxlength="10"><img @if($captcha['image'] !== '')src="{{ $captcha['image'] }}" @endif alt="{{ $ui['captcha'] }}" data-captcha-image><button type="button" class="cms-form-captcha-refresh" data-captcha-refresh>{{ $ui['captcha_refresh'] }}</button></div>
<input type="hidden" name="_captcha_id" value="{{ $captcha['id'] }}">
<noscript><p class="cms-form-noscript">{{ $ui['captcha_noscript'] }}</p></noscript>
<p class="cms-form-error" data-error role="alert" hidden></p>
</div>
@endif
<div class="cms-form-message" data-form-error role="alert" hidden></div>
<div class="cms-form-actions"><button type="submit" class="cms-form-submit">{{ $form['settings']['submit_text'] !== '' ? $form['settings']['submit_text'] : $ui['submit'] }}</button></div>
</form>
