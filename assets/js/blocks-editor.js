/**
 * Mango Dragon — Block editor registrations
 * Provides edit-mode previews for the 3 scroll band blocks.
 * save() returns null → server-side rendered via render_callback in PHP.
 */
( function () {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var el                = wp.element.createElement;

    function makePlaceholder( label, icon ) {
        return function EditBand() {
            return el( 'div', {
                style: {
                    padding:         '18px 24px',
                    background:      '#f7f4eb',
                    border:          '2px dashed #b3b0a7',
                    fontFamily:      "'Space Mono', 'Courier New', monospace",
                    fontSize:        '12px',
                    color:           '#333333',
                    textAlign:       'center',
                    letterSpacing:   '0.1em',
                    textTransform:   'uppercase',
                    borderRadius:    '2px',
                }
            },
                el( 'div', { style: { fontSize: '22px', marginBottom: '6px' } }, icon ),
                el( 'strong', { style: { display: 'block', fontSize: '13px' } }, label ),
                el( 'span', { style: { opacity: 0.55, fontSize: '11px', marginTop: '4px', display: 'block' } },
                    'Bande défilante — rendu côté serveur'
                )
            );
        };
    }

    registerBlockType( 'mango-dragon/band-artistes', {
        edit: makePlaceholder( 'Bande Artistes', '⟵ 🎤 ⟶' ),
        save: function () { return null; },
    } );

    registerBlockType( 'mango-dragon/band-releases', {
        edit: makePlaceholder( 'Bande Releases', '⟵ 💿 ⟶' ),
        save: function () { return null; },
    } );

    registerBlockType( 'mango-dragon/band-photos', {
        edit: makePlaceholder( 'Bande Studio', '⟵ 📷 ⟶' ),
        save: function () { return null; },
    } );

} )();
