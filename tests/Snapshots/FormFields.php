<?php

namespace Snapshots;

/**
 * One of every form field mPDF draws, the same page for the static, active and PDF/A documents
 */
trait FormFields
{

	/**
	 * The fields, a labelled row each, in a form of their own
	 *
	 * @return string
	 */
	private function formFields()
	{
		$image = __DIR__ . '/../data/img/exif-orientation-none.jpg';

		$rows = [
			'Text' => '<input type="text" name="text" value="A value" size="30" />',
			'Empty text' => '<input type="text" name="empty" size="30" />',
			'Password' => '<input type="password" name="password" value="secret" size="20" />',
			'Read-only' => '<input type="text" name="readonly" value="Read-only value" readonly="readonly" size="30" />',
			'Disabled' => '<input type="text" name="disabled" value="Disabled value" disabled="disabled" size="30" />',
			'Required' => '<input type="text" name="required" value="Required value" required="required" size="30" />',
			'Max length' => '<input type="text" name="maxlength" value="12345" maxlength="5" size="8" />',
			'Short text area' => '<textarea name="short" rows="2" cols="40">One line.</textarea>',
			'Long text area' => '<textarea name="long" rows="4" cols="40">A longer value that wraps over several lines of'
				. ' the text area, so that the words have to break at the edge of the field more than once.</textarea>',
			'Combo box' => '<select name="combo"><option value="1">First option</option>'
				. '<option value="2" selected="selected">Second option, selected</option><option value="3">Third option</option></select>',
			'List box' => '<select name="list" size="3"><option value="1">First</option><option value="2" selected="selected">Second, selected</option>'
				. '<option value="3">Third</option><option value="4">Fourth</option></select>',
			'Multiple' => '<select name="multiple" size="4" multiple="multiple"><option value="1" selected="selected">First, selected</option>'
				. '<option value="2">Second</option><option value="3" selected="selected">Third, selected</option><option value="4">Fourth</option></select>',
			'Checkboxes' => '<input type="checkbox" name="checked" value="yes" checked="checked" /> Checked'
				. ' <input type="checkbox" name="unchecked" value="yes" /> Unchecked',
			'Radio group' => '<input type="radio" name="group" value="a" /> A <input type="radio" name="group" value="b" checked="checked" /> B, checked'
				. ' <input type="radio" name="group" value="c" /> C',
			'Buttons' => '<input type="submit" name="submit" value="Submit" /> <input type="reset" name="reset" value="Reset" />'
				. ' <input type="button" name="button" value="Button" onclick="app.alert(1)" />',
			'Image button' => '<input type="image" name="image" src="' . $image . '" width="12mm" />',
			'Hidden' => '<input type="hidden" name="hidden" value="hidden value" /> (a hidden input, which draws nothing)',
			'Styled' => '<input type="text" name="styled" value="Styled value" size="24"'
				. ' style="font-size: 14pt; color: #aa0000; background-color: #ffffdd; border: 0.5mm solid #0066cc" />',
			'Arabic' => '<input type="text" name="arabic" value="مرحبا بالعالم" size="24" style="font-family: dejavusans" />',
		];

		$html = '<style>table.fields td { padding: 1mm 2mm 1mm 0; vertical-align: top; } td.label { width: 32mm; font-weight: bold; }</style>'
			. '<h2>Form fields</h2><form action="https://example.com/submit" method="post"><table class="fields">';
		foreach ($rows as $label => $field) {
			$html .= '<tr><td class="label">' . $label . '</td><td>' . $field . '</td></tr>';
		}

		return $html . '</table></form>';
	}

}
