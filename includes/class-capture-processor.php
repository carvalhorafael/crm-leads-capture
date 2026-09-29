<?php
/**
 * Provider-agnostic capture profile processor.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Processor {
	private CRM_Leads_Capture_Profile_Registry $profiles;

	private CRM_Leads_Capture_Provider_Registry $providers;

	private CRM_Leads_Capture_Logger $logger;

	/** @var callable */
	private $active_provider_resolver;

	/** @var callable */
	private $nonce_verifier;

	public function __construct(
		CRM_Leads_Capture_Profile_Registry $profiles,
		CRM_Leads_Capture_Provider_Registry $providers,
		callable $active_provider_resolver,
		callable $nonce_verifier,
		?CRM_Leads_Capture_Logger $logger = null
	) {
		$this->profiles                 = $profiles;
		$this->providers                = $providers;
		$this->active_provider_resolver = $active_provider_resolver;
		$this->nonce_verifier           = $nonce_verifier;
		$this->logger                   = $logger ?: new CRM_Leads_Capture_Logger();
	}

	/**
	 * @param array<string, mixed> $input Untrusted submitted fields.
	 * @param array<string, mixed> $trusted_context Context resolved by the server-side adapter.
	 */
	public function process( string $profile_slug, array $input, array $trusted_context = array() ): CRM_Leads_Capture_Result {
		$profile = $this->profiles->resolve( $profile_slug );
		if ( null === $profile ) {
			return $this->failure( 404, 'profile_not_found' );
		}

		$nonce = $input[ $profile->nonce_field() ] ?? '';
		if ( ! is_scalar( $nonce ) || ! (bool) call_user_func( $this->nonce_verifier, (string) $nonce, $profile->nonce_action() ) ) {
			return $this->failure( 403, 'invalid_nonce' );
		}

		$honeypot = $input[ $profile->honeypot_field() ] ?? '';
		if ( ! is_scalar( $honeypot ) || '' !== trim( (string) $honeypot ) ) {
			return $this->failure( 400, 'spam' );
		}

		$groups = array(
			CRM_Leads_Capture_Field::GROUP_LEAD     => array(),
			CRM_Leads_Capture_Field::GROUP_TRACKING => array(),
			CRM_Leads_Capture_Field::GROUP_CONSENT  => array(),
			CRM_Leads_Capture_Field::GROUP_CUSTOM   => array(),
		);
		$field_errors = array();

		foreach ( $profile->fields() as $field ) {
			$provided = array_key_exists( $field->name(), $input );
			if ( ! $provided && ! $field->is_required() ) {
				continue;
			}

			$result = $field->normalize( $provided ? $input[ $field->name() ] : null );
			if ( ! $result->is_successful() ) {
				$field_errors[ $field->name() ] = (string) ( $result->data()['code'] ?? 'invalid' );
				continue;
			}

			$groups[ $field->group() ][ $field->name() ] = $result->data()['value'] ?? '';
		}

		if ( array() !== $field_errors ) {
			$this->logger->debug(
				'Capture profile validation failed.',
				array(
					'profile_slug' => $profile->slug(),
					'field_names'  => array_keys( $field_errors ),
				)
			);

			return CRM_Leads_Capture_Result::failure(
				422,
				'Submission contains invalid fields.',
				array(
					'code'         => 'invalid_fields',
					'field_errors' => $field_errors,
				)
			);
		}

		$submission = new CRM_Leads_Capture_Submission(
			$profile->slug(),
			$groups[ CRM_Leads_Capture_Field::GROUP_LEAD ],
			$groups[ CRM_Leads_Capture_Field::GROUP_TRACKING ],
			$groups[ CRM_Leads_Capture_Field::GROUP_CONSENT ],
			$groups[ CRM_Leads_Capture_Field::GROUP_CUSTOM ],
			array_merge( $profile->context(), $trusted_context )
		);

		$provider_id = $this->normalize_key( (string) call_user_func( $this->active_provider_resolver ) );
		$provider    = $this->providers->get( $provider_id );
		if ( null === $provider ) {
			return $this->failure( 503, 'provider_unavailable' );
		}

		$provider_result = $provider->send_lead(
			$submission->to_array(),
			array_merge(
				$profile->provider_config( $provider_id ),
				array(
					'capture_profile'   => $profile->slug(),
					'submission_context' => array_merge( $profile->context(), $trusted_context ),
				)
			)
		);

		if ( ! $provider_result->is_successful() ) {
			$this->logger->debug(
				'Capture profile provider request failed.',
				array(
					'profile_slug' => $profile->slug(),
					'provider'     => $provider_id,
					'status_code'  => $provider_result->status_code(),
				)
			);

			return $this->failure( 502, 'provider_error' );
		}

		return CRM_Leads_Capture_Result::success(
			200,
			'Submission processed.',
			array(
				'code'         => 'success',
				'profile_slug' => $profile->slug(),
				'provider'     => $provider_id,
				'success'      => $profile->success_behavior(),
			)
		);
	}

	private function failure( int $status_code, string $code ): CRM_Leads_Capture_Result {
		$messages = array(
			'profile_not_found'    => 'Capture profile not found.',
			'invalid_nonce'        => 'Submission could not be validated.',
			'spam'                 => 'Submission could not be processed.',
			'provider_unavailable' => 'Capture provider is unavailable.',
			'provider_error'       => 'Capture provider request failed.',
		);

		return CRM_Leads_Capture_Result::failure(
			$status_code,
			$messages[ $code ] ?? 'Submission could not be processed.',
			array( 'code' => $code )
		);
	}

	private function normalize_key( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $value ) );
	}
}
