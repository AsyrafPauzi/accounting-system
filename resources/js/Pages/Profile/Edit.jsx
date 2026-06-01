import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import AppearanceForm from './Partials/AppearanceForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Profile</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Your account</h1>
                    <p className="text-ink-muted text-sm">Manage how you sign in and how the app feels.</p>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="max-w-4xl space-y-6">
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm">
                    <AppearanceForm className="max-w-xl" />
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm">
                    <DeleteUserForm className="max-w-xl" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
