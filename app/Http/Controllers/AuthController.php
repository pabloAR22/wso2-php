<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $client = new Client();

        try {
            $url = env('KEYCLOAK_BASE_URL').'/realms/'.env('KEYCLOAK_REALM').'/protocol/openid-connect/token';

            $body = [
                'grant_type' => 'password',
                'client_id' => env('KEYCLOAK_CLIENT_ID'),
                'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
                'username' => $request->username,
                'password' => $request->password,
            ];

            $response = $client->post($url, [
                'headers' => [
                    'accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $body,
                'verify' => false,
            ]);

            return response()->json(json_decode($response->getBody(), true), $response->getStatusCode());
        } catch (\Exception $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['error'])) {
                return response()->json([
                    'error' => $decodedResponse['error'],
                    'message' => $decodedResponse['error_description'] ?? 'Error desconocido',
                ], $e->getCode() ?: 400);
            }

            return response()->json([
                'error' => 'Error al autenticar',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al autenticar',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
            'email' => 'required|email',
        ]);

        $accessToken = $this->getManagementAccessToken();
        $client = new Client();

        try {
            $response = $client->post('http://localhost:8080/admin/realms/'.env('KEYCLOAK_REALM').'/users', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
                'json' => [
                    'username' => $request->username,
                    'enabled' => true,
                    'email' => $request->email,
                    'credentials' => [
                        [
                            'type' => 'password',
                            'value' => $request->password,
                            'temporary' => false,
                        ],
                    ],
                ],
            ]);

            return response()->json(['Message' => 'Usuario creado correctamente, por favor verificar email'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    private function getManagementAccessToken()
    {
        $client = new Client();

        try {
            $response = $client->post(env('KEYCLOAK_TOKEN_URL'), [
                'form_params' => [
                'username' => env('KEYCLOAK_ADMINISTRATION_USERNAME'),
                'password' => env('KEYCLOAK_ADMINISTRATION_PASSWORD'),
                'grant_type' => 'password',
                'client_id' => env('KEYCLOAK_CLIENT_ID'),
                'client_secret' => env('KEYCLOAK_CLIENT_SECRET')
            ],
                'verify' => false,
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener el token de acceso: ' . $e->getMessage());
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $token = $this->getManagementAccessToken();
            $userId = $this->validateUserExists($request->user);

            $client = new \GuzzleHttp\Client();

            $response = $client->put('http://localhost:8080/admin/realms/'.env('KEYCLOAK_REALM').'/users/'.$userId.'/reset-password-email', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function resetPasswordV2(Request $request)
    {
        try {
            $token = $this->getManagementAccessToken();
            $userId = $this->validateUserExists($request->username);

            $client = new \GuzzleHttp\Client();

            $response = $client->put('http://localhost:8080/admin/realms/'.env('KEYCLOAK_REALM').'/users/'.$userId.'/reset-password', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'type' => 'password',
                    'value' => $request->password,
                    'temporary' => false,
                ],
                'verify' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

    }

    // public function searchUserById(Request $request)
    // {
    //     $request->validate([
    //         'idUser' => 'required'
    //     ]);

    //     $accessToken = $this->getManagementAccessToken();
    //     $client = new Client();

    //     try {
    //         $response = $client->get('https://localhost:9443/wso2/scim/Users/'.$request->idUser, [
    //             'headers' => [
    //                 'Authorization' => 'Bearer ' . $accessToken,
    //                 'Content-Type' => 'application/json',
    //             ],
    //             'verify' => false,
    //         ]);

    //         $responseBody = $response->getBody()->getContents();
    //         $userData = json_decode($responseBody, true);

    //         return response()->json($userData, 201);
    //     } catch (\Throwable $e) {
    //         return response()->json(['error' => $e->getMessage()], 400);
    //     }
    // }

    private function getEmailByUser($id)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', 'https://localhost:9443/scim2/Users/'."$id", [
                'headers' => [
                    'Authorization' => 'Basic YWRtaW46YWRtaW4='
                ],
                'verify' => false,
            ]);
            $dataUser = json_decode($response->getBody(), true);
            if(!empty($dataUser['emails'][0])){
                return response()->json([
                    'status' => true,
                    'email' => $dataUser['emails'][0]
                ], 200);
            }else{
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuario no encontrado, favor contactar a soporte'
                ],404);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'msg' => $th->getMessage()
            ],500);
        }
    }

    public function validateUserExists($user)
    {
        $accessToken = $this->getManagementAccessToken();
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get('http://localhost:8080/admin/realms/'.env('KEYCLOAK_REALM').'/users', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'username' => $user
                ],
                'verify' => false,
            ]);

            $userData = json_decode($response->getBody(), true);
            $userID = $userData[0]['id'];

            if($userID) {
                return $userID;
            } else {
                return response()->json(['error' => 'No se encuentra el user ID'], 404);
            }

        } catch (RequestException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
