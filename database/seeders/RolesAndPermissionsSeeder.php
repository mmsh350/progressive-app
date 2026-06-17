<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\State;
use App\Models\AgentProfile;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            'manage-roles',
            'manage-settings',
            'manage-campaigns',
            'manage-coordinators',
            'manage-agents',
            'manage-occupations',
            'manage-rewards',
            'manage-wishes',
            'view-analytics',
            'export-reports',
            'view-submissions',
            'edit-submissions',
            'approve-submissions',
            'delete-submissions',
            'agent-registration',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Create Roles and Assign Permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        // Super Admin gets all permissions implicitly via Gate::before in AuthServiceProvider, but let's assign directly.
        $superAdminRole->syncPermissions($permissions);

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->syncPermissions([
            'manage-campaigns',
            'manage-coordinators',
            'manage-agents',
            'manage-occupations',
            'manage-rewards',
            'manage-wishes',
            'view-analytics',
            'export-reports',
            'view-submissions',
            'edit-submissions',
            'approve-submissions',
            'delete-submissions',
        ]);

        $coordinatorRole = Role::firstOrCreate(['name' => 'State Coordinator']);
        $coordinatorRole->syncPermissions([
            'view-submissions',
            'export-reports',
            'view-analytics',
        ]);

        $agentRole = Role::firstOrCreate(['name' => 'Agent']);
        $agentRole->syncPermissions([
            'agent-registration',
            'view-submissions',
        ]);

        // 3. Create Default Users
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@apc2027.org'],
            [
                'name' => 'Super Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('Super Admin');

        $admin = User::firstOrCreate(
            ['email' => 'admin@apc2027.org'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('Admin');

        // Let's get Lagos state for coordinator/agent setup
        $lagos = State::where('code', 'LA')->first();
        $lagosId = $lagos ? $lagos->id : null;

        $coordinator = User::firstOrCreate(
            ['email' => 'coordinator@apc2027.org'],
            [
                'name' => 'Lagos Coordinator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $coordinator->assignRole('State Coordinator');
        
        if ($lagosId) {
            AgentProfile::firstOrCreate(
                ['user_id' => $coordinator->id],
                [
                    'state_id' => $lagosId,
                    'status' => 'active',
                ]
            );
        }

        $agent = User::firstOrCreate(
            ['email' => 'agent@apc2027.org'],
            [
                'name' => 'Lagos Agent',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $agent->assignRole('Agent');

        if ($lagosId) {
            AgentProfile::firstOrCreate(
                ['user_id' => $agent->id],
                [
                    'state_id' => $lagosId,
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
