import { forwardRef, useImperativeHandle, useRef, useMemo } from '@wordpress/element';
import CodeMirror, { type ReactCodeMirrorRef } from '@uiw/react-codemirror';
import { html } from '@codemirror/lang-html';
import { lintGutter, linter, type Diagnostic } from '@codemirror/lint';
import { EditorView } from '@codemirror/view';

export interface MjmlError {
	line?: number;
	message?: string;
}

export interface CodeEditorHandle {
	insertAtCursor: ( text: string ) => void;
}

interface Props {
	value: string;
	errors: MjmlError[];
	onChange: ( v: string ) => void;
}

const MjmlCodeEditor = forwardRef< CodeEditorHandle, Props >( ( { value, errors, onChange }, ref ) => {
	const cm = useRef< ReactCodeMirrorRef >( null );

	useImperativeHandle( ref, () => ( {
		insertAtCursor( text: string ) {
			const view = cm.current?.view;
			if ( ! view ) {
				return;
			}
			const { from, to } = view.state.selection.main;
			view.dispatch( {
				changes: { from, to, insert: text },
				selection: { anchor: from + text.length },
			} );
			view.focus();
		},
	} ) );

	// Map MJML compile errors to line diagnostics (re-created when errors change).
	const extensions = useMemo(
		() => [
			html(),
			EditorView.lineWrapping,
			lintGutter(),
			linter( ( view ) =>
				errors.map( ( e ): Diagnostic => {
					const lineNo = Math.min( Math.max( 1, e.line || 1 ), view.state.doc.lines );
					const line = view.state.doc.line( lineNo );
					return { from: line.from, to: line.to, severity: 'error', message: e.message || 'MJML error' };
				} )
			),
		],
		[ errors ]
	);

	return (
		<CodeMirror
			ref={ cm }
			value={ value }
			extensions={ extensions }
			onChange={ onChange }
			basicSetup={ { lineNumbers: true, bracketMatching: true, foldGutter: true, highlightActiveLine: true } }
		/>
	);
} );

export default MjmlCodeEditor;
