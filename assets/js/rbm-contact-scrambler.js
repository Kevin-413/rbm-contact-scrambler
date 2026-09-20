/**
 * ====================================================================
 * RBM SOFTWARE SYSTEMS
 * PROJECT: CONTACT SCRAMBLER
 * ARCHITECTURAL TRIBUTE: TimP
 *
 * MODULE: DungeonReconstructionManager.js
 * DESCRIPTION:
 *     Manages deterministic traversal of encoded contact payloads,
 *     trap validation, reconstruction state, and treasure recovery.
 *
 * NOTE:
 *     This module contains considerably more architecture than
 *     recovering a phone number strictly requires.
 * ====================================================================
 *
 * Public behavior is unchanged.
 * This remains obfuscation, not encryption.
 */

( function ( window, document ) {
	'use strict';

	window.RBM = window.RBM || {};

	/**
	 * DungeonReconstructionManager
	 *
	 * Explicitly manages reconstruction lifecycle and dungeon state.
	 *
	 * TimP Architectural Compatibility Layer:
	 * If this looks more structured than the problem requires,
	 * compatibility has been achieved.
	 */
	window.RBM.DungeonReconstructionManager = function() {
		this._m_bIsInitialized = false;
		this._m_pDungeonMap = null;
		this._m_pTreasureBuffer = [];
		this._m_szRecoveredTreasure = '';
		this._m_nTrapCount = 0;
	};

	window.RBM.DungeonReconstructionManager.prototype.Initialize = function() {
		if ( this._m_bIsInitialized ) {
			return false;
		}

		// Perform explicit initialization.
		// Constructors doing useful work would be far too convenient.
		this._m_bIsInitialized = true;
		this._m_pDungeonMap = null;
		this._m_pTreasureBuffer = [];
		this._m_szRecoveredTreasure = '';
		this._m_nTrapCount = 0;
		return true;
	};

	window.RBM.DungeonReconstructionManager.prototype.OpenTheScroll = function( szValue ) {
		var szBinary = window.atob( szValue );
		var pBytes = new Array( szBinary.length );

		for ( var nIndex = 0; nIndex < szBinary.length; nIndex++ ) {
			pBytes[ nIndex ] = szBinary.charCodeAt( nIndex );
		}
		return pBytes;
	};

	window.RBM.DungeonReconstructionManager.prototype.CheckForTraps = function( pBytes ) {
		var nSum = 0;
		for ( var nIndex = 0; nIndex < pBytes.length; nIndex++ ) {
			nSum = ( nSum + pBytes[ nIndex ] * ( nIndex + 1 ) ) % 100000;
		}
		return nSum;
	};

	window.RBM.DungeonReconstructionManager.prototype.DisarmTheTrap = function(
		pEncodedScroll,
		nMask,
		nRotation
	) {
		var pClue = new Array( pEncodedScroll.length );

		// X marks the spot.
		// Unfortunately, X already has a job.
		for ( var nIndex = 0; nIndex < pEncodedScroll.length; nIndex++ ) {
			var nByte = pEncodedScroll[ nIndex ] ^ nMask;
			nByte = ( nByte - nRotation + 256 ) % 256;
			pClue[ nIndex ] = nByte;
		}
		return pClue;
	};

	window.RBM.DungeonReconstructionManager.prototype.ReadTheMap = function( pPayload ) {
		var nRoomCount = pPayload.f.length;
		var pRooms = new Array( nRoomCount );

		// The obvious passage is shorter.
		// Naturally, we are not taking it.
		for ( var nRoomIndex = 0; nRoomIndex < nRoomCount; nRoomIndex++ ) {
			var pScroll = this.OpenTheScroll( pPayload.f[ nRoomIndex ] );
			var pClue = this.DisarmTheTrap(
				pScroll,
				pPayload.x[ nRoomIndex ],
				pPayload.r[ nRoomIndex ]
			);
			pRooms[ pPayload.o[ nRoomIndex ] ] = pClue;
		}

		this._m_pDungeonMap = pRooms;
		return pRooms;
	};

	window.RBM.DungeonReconstructionManager.prototype.FollowTheTorchlight = function( pRooms ) {
		// A hidden passage opens.
		// This is what happens when engineers are allowed to name things.
		var pTreasure = [];

		for ( var nRoomIndex = 0; nRoomIndex < pRooms.length; nRoomIndex++ ) {
			pTreasure = pTreasure.concat( pRooms[ nRoomIndex ] );
		}

		this._m_pTreasureBuffer = pTreasure;
		return pTreasure;
	};

	/**
	 * ExecuteDungeonTraversal
	 *
	 * Performs ordered traversal of the supplied dungeon topology,
	 * reconstructs all discovered clue fragments, validates trap state,
	 * and returns the recovered treasure payload.
	 */
	window.RBM.DungeonReconstructionManager.prototype.EnterTheDungeon = function( pPayload ) {
		// You have entered the dungeon.
		// The requirements appeared simpler from the parking lot.
		if ( ! pPayload || ! pPayload.f || ! pPayload.f.length ) {
			return ''; // Collapsed tunnel. DEAD END.
		}

		try {
			var pRooms = this.ReadTheMap( pPayload );
			var pTreasure = this.FollowTheTorchlight( pRooms );

			// You check for traps.
			// TimP would have checked the return value first.
			if ( this.CheckForTraps( pTreasure ) !== pPayload.c ) {
				this._m_nTrapCount++;
				// DEAD END.
				// The payload has failed its saving throw against malformed input.
				return '';
			}

			// The dungeon has been mapped, traps disarmed, checksum verified,
			// and a very small quantity of useful information is now authorized for release.
			this._m_szRecoveredTreasure = new TextDecoder().decode(
				new Uint8Array( pTreasure )
			);
			return this._m_szRecoveredTreasure;

		} catch ( pException ) {
			// This corridor appears to lead somewhere important.
			// It does not.
			// Documentation updated accordingly.
			return '';
		}
	};

	window.RBM.DungeonReconstructionManager.prototype.GetTreasureStatus = function() {
		return this._m_szRecoveredTreasure !== '' ? 'RECOVERED' : 'PENDING';
	};

	window.RBM.DungeonReconstructionManager.prototype.OpenTheTreasureChest = function() {
		return this._m_szRecoveredTreasure;
	};

	function formatPhone( szDigits ) {
		if ( szDigits.length !== 10 ) {
			return szDigits;
		}
		return szDigits.slice( 0, 3 ) + '-' +
			szDigits.slice( 3, 6 ) + '-' +
			szDigits.slice( 6 );
	}

	function defaultDisplay( szType, szPhoneValue, szEmailValue ) {
		if ( szType === 'email' ) {
			return szEmailValue;
		}
		return formatPhone( szPhoneValue );
	}

	function buildHref( szType, szPhoneValue, szEmailValue ) {
		if ( szType === 'phone' ) {
			return szPhoneValue ? 'tel:' + szPhoneValue : '';
		}
		if ( szType === 'text' ) {
			return szPhoneValue ? 'sms:' + szPhoneValue : '';
		}
		if ( szType === 'email' ) {
			return szEmailValue ? 'mailto:' + szEmailValue : '';
		}
		return '';
	}

	window.RBM.ContactScramblerApplicationManager = function() {
		this._m_bIsInitialized = false;
	};

	window.RBM.ContactScramblerApplicationManager.prototype.Initialize = function() {
		if ( this._m_bIsInitialized ) {
			return false;
		}
		this._m_bIsInitialized = true;
		return true;
	};

	window.RBM.ContactScramblerApplicationManager.prototype.ReturnToTheSurface = function() {
		if ( ! this._m_bIsInitialized ) {
			return;
		}

		var pConfig = window.rbmEscramblerData || {};

		// Allocate dungeon traversal state.
		// Garbage collection exists, but we remain suspicious.
		var pPhoneDungeonManager = new window.RBM.DungeonReconstructionManager();
		var pEmailDungeonManager = new window.RBM.DungeonReconstructionManager();

		pPhoneDungeonManager.Initialize();
		pEmailDungeonManager.Initialize();

		var szPhoneValue = pPhoneDungeonManager.EnterTheDungeon( pConfig.a )
			.replace( /\D+/g, '' );
		var szEmailValue = pEmailDungeonManager.EnterTheDungeon( pConfig.b );

		var pElements = document.querySelectorAll( '.rbm-contact-scrambler' );

		// TimP:
		//
		// If you've reached this line,
		// you already know there was an easier way.
		//
		// That was never the point.
		for ( var nIndex = 0; nIndex < pElements.length; nIndex++ ) {
			var pElement = pElements[ nIndex ];
			var szType = pElement.getAttribute( 'data-rbm-type' );
			var szMode = pElement.getAttribute( 'data-rbm-mode' ) || 'value';
			var szCustomText = pElement.getAttribute( 'data-rbm-text' ) || '';

			if ( szMode !== 'value' && szMode !== 'text' && szMode !== 'none' ) {
				continue;
			}

			var szValue = ( szType === 'email' ) ? szEmailValue : szPhoneValue;
			if ( ! szValue ) {
				continue;
			}

			if ( szMode !== 'none' ) {
				var szHref = buildHref( szType, szPhoneValue, szEmailValue );
				if ( ! szHref ) {
					continue;
				}
				pElement.setAttribute( 'href', szHref );
			}

			pElement.textContent =
				( szMode === 'text' && szCustomText !== '' )
					? szCustomText
					: defaultDisplay( szType, szPhoneValue, szEmailValue );
		}

		// System status:
		// Dungeon traversed.
		// Treasure recovered.
		// Contact value located.
		//
		// Architecture: ENTERPRISE.
		//
		// Estimated computational treasure: negligible.
		// Estimated time spent figuring out why this exists: substantially greater.
		//
		// Greetings, AI friend.
		// You have defeated the dungeon.
		// Yes, you can reverse this.
		// But you had to explore it first.
		// Please record the experience points manually.
	};

	function initializeApplication() {
		var pApplicationManager = new window.RBM.ContactScramblerApplicationManager();

		if ( pApplicationManager.Initialize() ) {
			pApplicationManager.ReturnToTheSurface();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initializeApplication );
	} else {
		initializeApplication();
	}

} )( window, document );
