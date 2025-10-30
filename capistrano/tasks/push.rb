
# command: cap -T (lists all available capistrano commands)

namespace :push do
  set :continue, 'y'
  set :backup_file, "backup-" + Time.now.strftime("%d-%m-%Y-%H%M%S") + ".sql.gz"

  # command: cap staging push:db
  desc 'Upload local database to server'
  task :db do
    set :db_file, "db-push-#{fetch(:stage)}.sql.gz"
    run_locally do
      execute "wp db export --add-drop-table - | gzip > #{fetch(:db_file)}"
    end
    on release_roles(:staging) do
      upload! fetch(:db_file), "#{current_path}/#{fetch(:db_file)}"
    end
  end

  # command: cap staging push:import
  desc 'Import the local database file into the server'
  task :db_import do
    on release_roles(:staging) do
      set :db_file, "db-push-#{fetch(:stage)}.sql.gz"

      if "#{fetch(:stage)}" == 'production' then
        set :continue, ask('Are you SURE you want to do this?! About to wipe & replace the LIVE database [y/N]', 'n')
      end

      if fetch(:continue).downcase == 'y' then
        # Take a backup of server site... just in case!
        execute "cd #{current_path} && mkdir -p backups && wp db export --add-drop-table - | gzip > backups/#{fetch(:backup_file)}"

        execute "cd #{current_path} && gzip -dc #{fetch(:db_file)} | wp db import -"
        # Remove uploaded database backup for security
        execute "rm #{current_path}/#{fetch(:db_file)}"

        # set :old_url, ask('Old URL:', nil)
        # set :new_url, ask('New URL:', nil)
        execute "cd #{current_path} && wp search-replace #{fetch(:local_dev_url)} #{fetch(:site_url)}"

        # Reset password for User 1 to a preset password in config (a secure password)
        execute "cd #{current_path} && wp user update 1 --user_pass='#{fetch(:wp_admin_password)}'"
      end
    end
  end

  desc 'Run all export tasks'
  task :all do
    invoke 'push:db'
    invoke 'push:db_import'
  end
end
